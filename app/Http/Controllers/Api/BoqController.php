<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\BoqItemPriceSuggestion;
use App\Models\BoqPricingBatch;
use App\Jobs\ProcessBoqPricingItem;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use App\Services\GeminiPricingService;
use Barryvdh\DomPDF\Facade\Pdf;

class BoqController extends Controller
{
    public function startPricingBatch(Request $request, Boq $boq): JsonResponse
    {
        $user = $request->user();
        abort_unless($boq->project->user_id === $user->id || $boq->organisation_id === $user->organisation_id, 403);
        $location = $request->validate(['location' => ['required', 'string', 'max:255']])['location'];
        $itemIds = $boq->items()->pluck('id');
        $batch = BoqPricingBatch::create(['boq_id' => $boq->id, 'user_id' => $user->id, 'location' => $location, 'status' => 'running', 'total_items' => $itemIds->count()]);
        foreach ($itemIds as $itemId) ProcessBoqPricingItem::dispatch($batch->id, $itemId);
        if ($itemIds->isEmpty()) $batch->update(['status' => 'completed']);
        return response()->json(['success' => true, 'data' => $batch], 202);
    }

    public function pricingBatch(Request $request, BoqPricingBatch $batch): JsonResponse
    {
        abort_unless($batch->user_id === $request->user()->id, 403);
        return response()->json(['success' => true, 'data' => $batch]);
    }
    public function pdf(Request $request, Boq $boq)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(300);
        $user = $request->user();
        abort_unless($boq->project->user_id === $user->id || $boq->organisation_id === $user->organisation_id, 403);
        $items = $boq->items()->orderBy('id')->get();
        $html = '<h2>'.e($boq->name).'</h2><table width="100%" border="1" cellspacing="0" cellpadding="5"><tr><th>Item</th><th>Description</th><th>Unit</th><th>Qty</th><th>Rate</th><th>Amount</th></tr>';
        foreach ($items as $item) $html .= '<tr><td>'.e($item->item_code).'</td><td>'.e($item->description).'</td><td>'.e($item->unit).'</td><td>'.$item->quantity.'</td><td>'.$item->approved_rate.'</td><td>'.$item->amount.'</td></tr>';
        return Pdf::loadHTML($html.'</table>')->setPaper('a4', 'landscape')->download('boq-'.$boq->id.'.pdf');
    }
    public function priceAll(Request $request, Boq $boq, GeminiPricingService $gemini): JsonResponse
    {
        $user = $request->user();
        abort_unless($boq->project->user_id === $user->id || $boq->organisation_id === $user->organisation_id, 403);
        $location = $request->validate(['location' => ['required', 'string', 'max:255']])['location'];
        foreach ($boq->items as $item) {
            $result = $gemini->suggest(['description' => $item->description, 'unit' => $item->unit], $location, $item->currency);
            $item->update(['ai_suggested_rate' => $result['suggested_rate'], 'approved_rate' => $result['suggested_rate'], 'amount' => $item->quantity * $result['suggested_rate'], 'ai_confidence' => $result['confidence'] ?? null, 'pricing_source' => config('services.ai_provider'), 'pricing_date' => now()]);
            BoqItemPriceSuggestion::create(['boq_item_id' => $item->id, 'location' => $location, 'suggested_rate' => $result['suggested_rate'], 'confidence' => $result['confidence'] ?? null, 'explanation' => $result['explanation'] ?? null, 'currency' => $item->currency]);
        }
        return response()->json(['success' => true, 'data' => ['items_priced' => $boq->items->count()]]);
    }
    public function price(Request $request, BoqItem $boqItem, GeminiPricingService $gemini): JsonResponse
    {
        $user = $request->user();
        abort_unless($boqItem->boq->project->user_id === $user->id || $boqItem->boq->organisation_id === $user->organisation_id, 403);
        $validated = $request->validate(['location' => ['required', 'string', 'max:255']]);
        $result = $gemini->suggest(['description' => $boqItem->description, 'unit' => $boqItem->unit], $validated['location'], $boqItem->currency);
        $boqItem->update(['ai_suggested_rate' => $result['suggested_rate'], 'approved_rate' => $result['suggested_rate'], 'amount' => $boqItem->quantity * $result['suggested_rate'], 'ai_confidence' => $result['confidence'] ?? null, 'pricing_source' => config('services.ai_provider'), 'pricing_date' => now()]);

        return response()->json(['success' => true, 'data' => ['item' => $boqItem->fresh(), 'explanation' => $result['explanation'] ?? null]]);
    }
    public function show(Request $request, Boq $boq): JsonResponse
    {
        $user = $request->user();
        abort_unless($boq->project->user_id === $user->id || $boq->organisation_id === $user->organisation_id, 403);

        return response()->json(['success' => true, 'data' => $boq->load(['items' => fn ($query) => $query->orderBy('id')])]);
    }

    public function process(Request $request, Boq $boq): JsonResponse
    {
        set_time_limit(300);
        $user = $request->user();
        abort_unless($boq->project->user_id === $user->id || $boq->organisation_id === $user->organisation_id, 403);
        abort_unless($boq->source_type === 'excel', 422, 'Only Excel and CSV BOQs can be processed automatically.');

        $reader = ReaderFactory::createFromFile(Storage::path($boq->source_file_path));
        $reader->open(Storage::path($boq->source_file_path));
        $headers = null;
        $created = 0;
        $items = [];
        BoqItem::where('boq_id', $boq->id)->delete();

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $values = array_map(fn ($cell) => trim((string) ($cell->getValue() ?? '')), $row->getCells());
                if ($headers === null) {
                    $normalised = array_map(fn ($value) => strtoupper($value), $values);
                    if (in_array('DESCRIPTION', $normalised, true) && (in_array('QTY', $normalised, true) || in_array('QUANTITY', $normalised, true))) {
                        $headers = $normalised;
                    }
                    continue;
                }
                $description = $values[array_search('DESCRIPTION', $headers, true)] ?? '';
                if ($description === '') continue;
                $quantityIndex = $this->headerIndex($headers, ['QUANTITY', 'QTY']);
                $rateIndex = $this->headerIndex($headers, ['RATE']);
                $amountIndex = $this->headerIndex($headers, ['AMOUNT']);
                $itemIndex = $this->headerIndex($headers, ['ITEM']);
                $unitIndex = $this->headerIndex($headers, ['UNIT']);
                $quantity = (float) str_replace(',', '', $values[$quantityIndex] ?? 0);
                $rate = (float) str_replace(',', '', $values[$rateIndex] ?? 0);
                $amount = (float) str_replace(',', '', $values[$amountIndex] ?? 0);
                $items[] = ['boq_id' => $boq->id, 'item_code' => $values[$itemIndex] ?? null, 'description' => $description, 'unit' => $values[$unitIndex] ?? null, 'quantity' => $quantity, 'original_rate' => $rate ?: null, 'approved_rate' => $rate ?: null, 'amount' => $amount ?: $quantity * $rate, 'currency' => $boq->currency, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()];
                $created++;
                if (count($items) === 500) { DB::table('boq_items')->insert($items); $items = []; }
            }
        }
        $reader->close();
        if ($items) DB::table('boq_items')->insert($items);
        $boq->update(['status' => 'under_review']);
        return response()->json(['success' => true, 'data' => ['items_created' => $created]]);
    }

    private function headerIndex(array $headers, array $names): int|false
    {
        foreach ($headers as $index => $header) {
            foreach ($names as $name) {
                if (str_contains($header, $name)) return $index;
            }
        }

        return false;
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,pdf,jpg,jpeg,png', 'max:20480'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $user = $request->user();
        abort_unless($project->user_id === $user->id || $project->organisation_id === $user->organisation_id, 403);

        $file = $request->file('file');
        $path = $file->store("boqs/{$project->id}");
        $extension = strtolower($file->getClientOriginalExtension());

        $boq = Boq::create([
            'project_id' => $project->id,
            'organisation_id' => $user->organisation_id,
            'name' => ($validated['name'] ?? null) ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'currency' => $project->currency ?: 'UGX',
            'status' => 'uploaded',
            'source_type' => in_array($extension, ['xlsx', 'xls', 'csv']) ? 'excel' : ($extension === 'pdf' ? 'pdf' : 'scan'),
            'source_file_path' => $path,
        ]);

        return response()->json(['success' => true, 'data' => $boq], 201);
    }

    public function pricingHistory(Request $request, Boq $boq, string $location): JsonResponse
    {
        abort_unless($boq->project->user_id === $request->user()->id || $boq->organisation_id === $request->user()->organisation_id, 403);
        $itemIds = $boq->items()->pluck('id');
        $suggestions = BoqItemPriceSuggestion::query()
            ->whereIn('boq_item_id', $itemIds)
            ->where('location', $location)
            ->with('boqItem')
            ->latest()
            ->get();
        return response()->json(['success' => true, 'data' => $suggestions]);
    }
}
