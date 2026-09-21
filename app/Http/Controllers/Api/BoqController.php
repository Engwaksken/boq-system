<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProcessBoqRequest;
use App\Http\Requests\StoreBoqRequest;
use App\Http\Resources\BoqPricingJobResource;
use App\Http\Resources\BoqResource;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\BoqItemPriceSuggestion;
use App\Models\BoqPricingJob;
use App\Models\Project;
use App\Services\BoqPricingJobService;
use App\Services\BoqSpreadsheetImporter;
use App\Services\GeminiPricingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BoqController extends Controller
{
    private function canAccess(Boq $boq, int $userId, ?int $organisationId): bool
    {
        if ($organisationId !== null) {
            return $boq->organisation_id === $organisationId && $boq->project->organisation_id === $organisationId;
        }

        return $boq->organisation_id === null && $boq->project->organisation_id === null && $boq->project->user_id === $userId;
    }

    /**
     * List BOQs with pagination, search, and filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Boq::query()
            ->where(function ($q) use ($user) {
                $q->where('organisation_id', $user->organisation_id)
                    ->orWhere(function ($q2) use ($user) {
                        $q2->whereNull('organisation_id')
                            ->whereHas('project', fn ($q3) => $q3->where('user_id', $user->id));
                    });
            })
            ->with(['project', 'organisation'])
            ->withCount('items')
            ->latest();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhereHas('project', fn ($q2) => $q2->where('name', 'like', "%{$search}%"));
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Project filter
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        // Pagination
        $perPage = min((int) $request->get('per_page', 15), 100);
        $allowedPerPage = [10, 25, 50, 100];
        if (!in_array($perPage, $allowedPerPage)) {
            $perPage = 15;
        }

        $boqs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $boqs,
        ]);
    }

    /**
     * List BOQ items with pagination, search, and filtering.
     */
    public function items(Request $request, Boq $boq): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canAccess($boq, $user->id, $user->organisation_id), 403);
        abort_unless($user->hasPermission('boq.view'), 403);

        $query = $boq->items()
            ->with(['facility', 'bill', 'element', 'subElement', 'translations'])
            ->orderBy('id');

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('item_code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('work_category', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Pricing status filter
        if ($request->filled('pricing_status')) {
            $query->where('pricing_status', $request->pricing_status);
        }

        // Facility filter
        if ($request->filled('facility_id')) {
            $query->where('facility_id', $request->facility_id);
        }

        // Pagination
        $perPage = min((int) $request->get('per_page', 25), 100);
        $allowedPerPage = [10, 25, 50, 100];
        if (!in_array($perPage, $allowedPerPage)) {
            $perPage = 25;
        }

        $items = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function startPricingBatch(Request $request, Boq $boq): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canAccess($boq, $user->id, $user->organisation_id), 403);
        abort_unless($user->hasPermission('boq.edit'), 403);
        $validated = $request->validate([
            'location' => ['required', 'string', 'max:255'],
            'batch_size' => ['sometimes', 'integer', 'in:10,20,25'],
        ]);

        // A job already running keeps processing; hand back the same job so
        // the caller can continue polling it instead of starting a duplicate.
        $existing = BoqPricingJob::forBoq($boq->id)->active()->first();
        if ($existing) {
            return (new BoqPricingJobResource($existing))
                ->additional(['success' => true, 'message' => 'An active pricing job already exists for this BOQ.'])
                ->response()
                ->setStatusCode(202);
        }

        $job = app(BoqPricingJobService::class)->start(
            $boq,
            $user,
            $validated['location'],
            $validated['batch_size'] ?? 20,
        );

        return (new BoqPricingJobResource($job))
            ->additional(['success' => true, 'message' => 'Pricing job created and batch dispatch started.'])
            ->response()
            ->setStatusCode(202);
    }

    public function pricingBatch(Request $request, BoqPricingJob $batch): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $batch->user_id === $user->id
                || ($batch->organisation_id !== null && $batch->organisation_id === $user->organisation_id),
            403
        );

        return (new BoqPricingJobResource($batch))
            ->additional(['success' => true])
            ->response();
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
        $this->authorize('view', $boq);

        return (new BoqResource($this->loadPhaseFourRelations($boq)))
            ->additional(['success' => true])
            ->response();
    }

    public function item(Request $request, BoqItem $boqItem): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canAccess($boqItem->boq, $user->id, $user->organisation_id), 403);
        abort_unless($user->hasPermission('boq.view'), 403);

        $boqItem->load(['facility', 'bill', 'element', 'subElement', 'translations']);

        return response()->json([
            'success' => true,
            'data' => $boqItem,
        ]);
    }

    public function process(ProcessBoqRequest $request, Boq $boq, BoqSpreadsheetImporter $importer): JsonResponse
    {
        set_time_limit(300);
        $created = $importer->import($boq);
        $boq->update(['status' => 'under_review']);

        return (new BoqResource($this->loadPhaseFourRelations($boq->fresh() ?? $boq)))
            ->additional(['success' => true, 'meta' => ['items_imported' => $created]])
            ->response();
    }

    private function loadPhaseFourRelations(Boq $boq): Boq
    {
        return $boq->load([
            'facilities.bills.elements.subElements.items.translations',
            'facilities.bills.elements.items.translations',
            'facilities.bills.items.translations',
            'facilities.items.translations',
            'facilities.summaries',
            'facilities.bills.summaries',
            'items' => fn ($query) => $query->with([
                'facility',
                'bill',
                'element',
                'subElement',
                'translations',
            ])->orderBy('id'),
            'summaries.facility',
            'summaries.bill',
        ]);
    }

    public function store(StoreBoqRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $project = Project::findOrFail($validated['project_id']);

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

        return (new BoqResource($boq))
            ->additional(['success' => true])
            ->response()
            ->setStatusCode(201);
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
