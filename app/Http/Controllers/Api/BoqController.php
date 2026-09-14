<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBoqPricingItem;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\BoqItemPriceSuggestion;
use App\Models\BoqPricingBatch;
use App\Models\Project;
use App\Services\GeminiPricingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\Common\Creator\ReaderFactory;

class BoqController extends Controller
{
    private function canAccess(Boq $boq, int $userId, ?int $organisationId): bool
    {
        if ($organisationId !== null) {
            return $boq->organisation_id === $organisationId && $boq->project->organisation_id === $organisationId;
        }

        return $boq->organisation_id === null && $boq->project->organisation_id === null && $boq->project->user_id === $userId;
    }

    public function startPricingBatch(Request $request, Boq $boq): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canAccess($boq, $user->id, $user->organisation_id), 403);
        abort_unless($user->hasPermission('boq.edit'), 403);
        $location = $request->validate(['location' => ['required', 'string', 'max:255']])['location'];
        $itemIds = $boq->items()->pluck('id');
        $batch = BoqPricingBatch::create(['boq_id' => $boq->id, 'organisation_id' => $user->organisation_id, 'user_id' => $user->id, 'location' => $location, 'operation' => 'location_pricing', 'provider' => config('services.ai_provider'), 'current_stage' => 'running', 'status' => 'running', 'total_items' => $itemIds->count(), 'started_at' => now()]);
        foreach ($itemIds as $itemId) {
            ProcessBoqPricingItem::dispatch($batch->id, $itemId);
        }
        if ($itemIds->isEmpty()) {
            $batch->update(['status' => 'completed']);
        }

        return response()->json(['success' => true, 'data' => $batch], 202);
    }

    public function pricingBatch(Request $request, BoqPricingBatch $batch): JsonResponse
    {
        abort_unless($batch->user_id === $request->user()->id, 403);

        $pricedItems = BoqItemPriceSuggestion::query()
            ->whereIn('boq_item_id', $batch->boq->items()->pluck('id'))
            ->where('location', $batch->location)
            ->when($batch->started_at, fn ($query) => $query->where('created_at', '>=', $batch->started_at))
            ->distinct('boq_item_id')
            ->count('boq_item_id');
        if ($pricedItems > $batch->processed_items) {
            $batch->update(['processed_items' => $pricedItems]);
        }

        return response()->json(['success' => true, 'data' => $batch->fresh()]);
    }

    public function pdf(Request $request, Boq $boq)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(300);
        $user = $request->user();
        abort_unless($this->canAccess($boq, $user->id, $user->organisation_id), 403);
        $items = $boq->items()->orderBy('id')->get();
        $html = '<h2>'.e($boq->name).'</h2><table width="100%" border="1" cellspacing="0" cellpadding="5"><tr><th>Item</th><th>Description</th><th>Unit</th><th>Qty</th><th>Rate</th><th>Amount</th></tr>';
        foreach ($items as $item) {
            $html .= '<tr><td>'.e($item->item_code).'</td><td>'.e($item->description).'</td><td>'.e($item->unit).'</td><td>'.$item->quantity.'</td><td>'.$item->approved_rate.'</td><td>'.$item->amount.'</td></tr>';
        }

        return Pdf::loadHTML($html.'</table>')->setPaper('a4', 'landscape')->download('boq-'.$boq->id.'.pdf');
    }

    public function priceAll(Request $request, Boq $boq, GeminiPricingService $gemini): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canAccess($boq, $user->id, $user->organisation_id), 403);
        abort_unless($user->hasPermission('boq.edit'), 403);
        $location = $request->validate(['location' => ['required', 'string', 'max:255']])['location'];
        foreach ($boq->items as $item) {
            $result = $gemini->suggest(['description' => $item->description, 'unit' => $item->unit], $location, $item->currency);
            $item->update(['ai_suggested_rate' => $result['suggested_rate'], 'reviewed_rate' => null, 'approved_rate' => null, 'location' => $location, 'ai_confidence' => $result['confidence'] ?? null, 'pricing_source' => config('services.ai_provider'), 'pricing_date' => now(), 'status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null, 'approved_by' => null, 'approved_at' => null, 'rejected_by' => null, 'rejected_at' => null, 'rejection_reason' => null]);
            BoqItemPriceSuggestion::create(['boq_item_id' => $item->id, 'location' => $location, 'suggested_rate' => $result['suggested_rate'], 'confidence' => $result['confidence'] ?? null, 'explanation' => $result['explanation'] ?? null, 'currency' => $item->currency]);
        }
        $boq->update(['status' => 'under_review']);

        return response()->json(['success' => true, 'data' => ['items_priced' => $boq->items->count()]]);
    }

    public function price(Request $request, BoqItem $boqItem, GeminiPricingService $gemini): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canAccess($boqItem->boq, $user->id, $user->organisation_id), 403);
        abort_unless($user->hasPermission('boq.edit'), 403);
        $validated = $request->validate(['location' => ['required', 'string', 'max:255']]);
        $result = $gemini->suggest(['description' => $boqItem->description, 'unit' => $boqItem->unit], $validated['location'], $boqItem->currency);
        $boqItem->update(['ai_suggested_rate' => $result['suggested_rate'], 'reviewed_rate' => null, 'approved_rate' => null, 'location' => $validated['location'], 'ai_confidence' => $result['confidence'] ?? null, 'pricing_source' => config('services.ai_provider'), 'pricing_date' => now(), 'status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null, 'approved_by' => null, 'approved_at' => null, 'rejected_by' => null, 'rejected_at' => null, 'rejection_reason' => null]);
        $boqItem->boq()->update(['status' => 'under_review']);

        return response()->json(['success' => true, 'data' => ['item' => $boqItem->fresh(), 'explanation' => $result['explanation'] ?? null]]);
    }

    public function show(Request $request, Boq $boq): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canAccess($boq, $user->id, $user->organisation_id), 403);

        return response()->json(['success' => true, 'data' => $boq->load(['items' => fn ($query) => $query->orderBy('id')])]);
    }

    public function process(Request $request, Boq $boq): JsonResponse
    {
        set_time_limit(300);
        $user = $request->user();
        abort_unless($this->canAccess($boq, $user->id, $user->organisation_id), 403);
        abort_unless($user->hasPermission('boq.edit'), 403);
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
                if ($description === '') {
                    continue;
                }
                $quantityIndex = $this->headerIndex($headers, ['QUANTITY', 'QTY']);
                $rateIndex = $this->headerIndex($headers, ['RATE']);
                $amountIndex = $this->headerIndex($headers, ['AMOUNT']);
                $itemIndex = $this->headerIndex($headers, ['ITEM']);
                $unitIndex = $this->headerIndex($headers, ['UNIT']);
                $quantity = (float) str_replace(',', '', $values[$quantityIndex] ?? 0);
                $rate = $rateIndex !== false ? (float) str_replace(',', '', $values[$rateIndex] ?? 0) : null;
                $amount = $amountIndex !== false ? (float) str_replace(',', '', $values[$amountIndex] ?? 0) : null;
                if ($rate === null && $amount !== null && $quantity > 0) {
                    $rate = round($amount / $quantity, 2);
                }
                if ($amount === null && $rate !== null) {
                    $amount = round($quantity * $rate, 2);
                }
                $items[] = ['boq_id' => $boq->id, 'item_code' => $values[$itemIndex] ?? null, 'description' => $description, 'unit' => $values[$unitIndex] ?? null, 'quantity' => $quantity, 'original_rate' => $rate, 'approved_rate' => null, 'amount' => $amount ?? 0, 'currency' => $boq->currency, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()];
                $created++;
                if (count($items) === 500) {
                    DB::table('boq_items')->insert($items);
                    $items = [];
                }
            }
        }
        $reader->close();
        if ($items) {
            DB::table('boq_items')->insert($items);
        }
        $boq->update(['status' => 'under_review']);

        return response()->json(['success' => true, 'data' => ['items_created' => $created]]);
    }

    private function headerIndex(array $headers, array $names): int|false
    {
        foreach ($headers as $index => $header) {
            foreach ($names as $name) {
                if (str_contains($header, $name)) {
                    return $index;
                }
            }
        }

        return false;
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'file' => ['required', 'file', 'mimes:xlsx,csv,pdf,jpg,jpeg,png', 'max:20480'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $user = $request->user();
        $tenantAccess = $user->organisation_id !== null && $project->organisation_id === $user->organisation_id;
        $personalAccess = $project->user_id === $user->id && $project->organisation_id === null && $user->organisation_id === null;
        abort_unless($tenantAccess || $personalAccess, 403);

        $file = $request->file('file');
        $path = $file->store("boqs/{$project->id}");
        $extension = strtolower($file->getClientOriginalExtension());

        $boq = Boq::create([
            'project_id' => $project->id,
            'organisation_id' => $project->organisation_id,
            'name' => ($validated['name'] ?? null) ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'currency' => $project->currency ?: 'UGX',
            'status' => 'uploaded',
            'source_type' => in_array($extension, ['xlsx', 'csv']) ? 'excel' : ($extension === 'pdf' ? 'pdf' : 'scan'),
            'source_file_path' => $path,
        ]);

        return response()->json(['success' => true, 'data' => $boq], 201);
    }

    public function pricingHistory(Request $request, Boq $boq, ?string $location = null): JsonResponse
    {
        abort_unless($this->canAccess($boq, $request->user()->id, $request->user()->organisation_id), 403);
        $itemIds = $boq->items()->pluck('id');
        $suggestions = BoqItemPriceSuggestion::query()
            ->whereIn('boq_item_id', $itemIds)
            ->when($location !== null, fn ($query) => $query->where('location', $location))
            ->with('boqItem')
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $suggestions]);
    }
}
