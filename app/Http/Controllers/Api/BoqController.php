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
        $totals = app(\App\Services\BoqTotals::class)->forBoqs($boqs->getCollection()->pluck('id')->all());
        $boqs->getCollection()->each(fn (Boq $boq) => $boq->setAttribute('totals', $totals[$boq->id]));

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

    /**
     * Email the branded BOQ PDF.
     */
    public function shareEmail(Request $request, Boq $boq, \App\Services\BoqShareService $shares): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canAccess($boq, $user->id, $user->organisation_id), 403);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:200'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $shares->email($boq, $user, $data['email'], $data['subject'] ?? null, $data['message'] ?? null);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'error_code' => 'SHARE_FAILED',
                'message' => 'The email could not be sent. Please try again later.',
            ], 502);
        }

        return response()->json(['success' => true, 'message' => 'BOQ sent to '.$data['email'].'.']);
    }

    /**
     * Signed download link and a ready-made message for WhatsApp / device sharing.
     */
    public function shareLink(Request $request, Boq $boq, \App\Services\BoqShareService $shares): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canAccess($boq, $user->id, $user->organisation_id), 403);

        $link = $shares->link($boq);

        return response()->json([
            'success' => true,
            'data' => [
                'url' => $link,
                'expires_in_days' => \App\Services\BoqShareService::LINK_DAYS,
                'message' => $shares->message($boq, $link),
                'whatsapp_url' => 'https://wa.me/?text='.rawurlencode($shares->message($boq, $link)),
                'filename' => app(\App\Services\BoqPdfService::class)->filename($boq),
            ],
        ]);
    }

    public function pdf(Request $request, Boq $boq, \App\Services\BoqPdfService $pdfs)
    {
        $user = $request->user();
        abort_unless($this->canAccess($boq, $user->id, $user->organisation_id), 403);

        try {
            $pdf = $pdfs->pdf($boq);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'error_code' => 'PDF_FAILED',
                'message' => 'The PDF could not be generated. Please try again.',
            ], 500);
        }

        // ?inline=1 previews in the browser; otherwise download.
        $response = $request->boolean('inline')
            ? $pdf->stream($pdfs->filename($boq))
            : $pdf->download($pdfs->filename($boq));
        // Signatures and prices change: never serve a cached copy.
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

        return $response;
    }

    public function priceAll(Request $request, Boq $boq, GeminiPricingService $gemini): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canAccess($boq, $user->id, $user->organisation_id), 403);
        abort_unless($user->hasPermission('boq.edit'), 403);
        $location = $request->validate(['location' => ['required', 'string', 'max:255']])['location'];
        set_time_limit(300);

        // Never overwrite rates a reviewer has already approved.
        $items = $boq->items()->whereNull('approved_rate')->where('status', '!=', 'approved')->get();

        foreach ($items as $item) {
            $result = $gemini->suggest(['description' => $item->description, 'unit' => $item->unit], $location, $item->currency);
            $item->update(['ai_suggested_rate' => $result['suggested_rate'], 'reviewed_rate' => null, 'approved_rate' => null, 'location' => $location, 'ai_confidence' => $result['confidence'] ?? null, 'pricing_source' => config('services.ai_provider'), 'pricing_date' => now(), 'status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null, 'approved_by' => null, 'approved_at' => null, 'rejected_by' => null, 'rejected_at' => null, 'rejection_reason' => null]);
            BoqItemPriceSuggestion::create(['boq_item_id' => $item->id, 'location' => $location, 'suggested_rate' => $result['suggested_rate'], 'confidence' => $result['confidence'] ?? null, 'explanation' => $result['explanation'] ?? null, 'currency' => $item->currency]);
        }
        $boq->update(['status' => 'under_review']);

        return response()->json(['success' => true, 'data' => ['items_priced' => $items->count()]]);
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
        $boq->setAttribute('totals', app(\App\Services\BoqTotals::class)->forBoq($boq));

        return (new BoqResource($this->loadPhaseFourRelations($boq)))
            ->additional(['success' => true])
            ->response();
    }

    /**
     * Rename a BOQ, change its description or move it to another of the user's projects.
     */
    public function update(Request $request, Boq $boq): JsonResponse
    {
        $this->authorize('update', $boq);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'project_id' => ['sometimes', 'required', 'integer', 'exists:projects,id'],
        ]);

        $user = $request->user();
        $changes = [];

        if (array_key_exists('name', $validated)) {
            $changes['name'] = trim($validated['name']);
        }
        if (array_key_exists('description', $validated)) {
            $changes['description'] = $validated['description'] !== null ? trim($validated['description']) : null;
        }
        if (isset($validated['project_id']) && (int) $validated['project_id'] !== $boq->project_id) {
            $project = Project::findOrFail($validated['project_id']);
            // Only move a BOQ into a project the user can also access.
            $canUseProject = $user->organisation_id !== null
                ? $project->organisation_id === $user->organisation_id
                : $project->user_id === $user->id && $project->organisation_id === null;

            if (! $canUseProject) {
                throw \Illuminate\Validation\ValidationException::withMessages(['project_id' => __('Choose one of your projects.')]);
            }

            $changes['project_id'] = $project->id;
            $changes['organisation_id'] = $project->organisation_id;
        }

        $boq->update($changes);

        return (new BoqResource($boq->fresh()->load('project')))
            ->additional(['success' => true, 'message' => __('BOQ updated.')])
            ->response();
    }

    /**
     * CSV template of the BOQ's items for entering estimated rates.
     */
    public function estimatesTemplate(Request $request, Boq $boq): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('view', $boq);

        $name = \Illuminate\Support\Str::slug($boq->name ?: 'boq').'-estimated-prices.csv';

        return response(app(\App\Services\BoqEstimateImporter::class)->template($boq), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }

    /**
     * Apply a file of estimated rates to the BOQ's items.
     */
    public function uploadEstimates(Request $request, Boq $boq): JsonResponse
    {
        $this->authorize('update', $boq);

        $request->validate(['file' => \App\Services\BoqUploadNormalizer::rules()]);

        $result = app(\App\Services\BoqEstimateImporter::class)->import($boq, $request->file('file'));
        $totals = app(\App\Services\BoqTotals::class)->forBoq($boq);

        $message = $result['updated'] === 1
            ? '1 estimated price updated.'
            : "{$result['updated']} estimated prices updated.";
        if ($result['unmatched'] !== []) {
            $message .= ' '.count($result['unmatched']).' row(s) did not match a BOQ item.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $result + ['totals' => $totals],
        ]);
    }

    /**
     * Review, approve or reject the suggested prices of several items at once.
     * action: accept (use the suggested price as reviewed) | approve | reject (reason required)
     */
    public function bulkReview(Request $request, Boq $boq, \App\Services\BoqItemReview $review): JsonResponse
    {
        $this->authorize('update', $boq);
        $data = $request->validate([
            'action' => ['required', 'in:accept,approve,reject'],
            'item_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'item_ids.*' => ['integer'],
            'reason' => ['required_if:action,reject', 'nullable', 'string', 'max:2000'],
        ]);
        $user = $request->user();
        abort_if($data['action'] === 'approve' && ! $user->hasPermission('boq.approve'), 403);

        $ids = array_map('intval', $data['item_ids']);
        $result = match ($data['action']) {
            'accept' => $review->acceptSuggested($boq, $ids, $user->id),
            'approve' => $review->approve($boq, $ids, $user->id),
            'reject' => $review->reject($boq, $ids, (string) $data['reason'], $user->id),
        };

        return response()->json(['success' => true, 'data' => $result]);
    }

    /** Locations this BOQ has prices for, with totals. */
    public function locations(Request $request, Boq $boq, \App\Services\BoqLocationPricing $pricing): JsonResponse
    {
        $this->authorize('view', $boq);

        return response()->json(['success' => true, 'data' => $pricing->locations($boq)]);
    }

    /** Item rates side by side for 2 to 4 locations (keys from the locations list). */
    public function compareLocations(Request $request, Boq $boq, \App\Services\BoqLocationPricing $pricing): JsonResponse
    {
        $this->authorize('view', $boq);
        $keys = $request->validate(['locations' => ['required', 'array', 'min:2', 'max:4'], 'locations.*' => ['string', 'max:191']])['locations'];
        $comparison = $pricing->compare($boq, $keys);

        return response()->json(['success' => true, 'data' => [
            'locations' => $comparison['locations'],
            'rows' => array_map(fn ($row) => [
                'item_id' => $row['item']->id,
                'item_code' => $row['item']->item_code,
                'description' => $row['item']->description,
                'unit' => $row['item']->unit,
                'quantity' => (float) $row['item']->quantity,
                'rates' => $row['rates'],
                'lowest' => $row['lowest'],
            ], $comparison['rows']),
        ]]);
    }

    /** Use one location's saved prices as the BOQ's suggested prices. */
    public function useLocation(Request $request, Boq $boq, \App\Services\BoqLocationPricing $pricing): JsonResponse
    {
        $this->authorize('update', $boq);
        $key = \App\Models\BoqLocationPrice::keyFor($request->validate(['location' => ['required', 'string', 'max:255']])['location']);

        return response()->json(['success' => true, 'data' => $pricing->apply($boq, $key)]);
    }

    public function destroy(Request $request, Boq $boq): JsonResponse
    {
        $this->authorize('delete', $boq);

        $boq->delete();

        return response()->json([
            'success' => true,
            'message' => __('BOQ deleted successfully.'),
        ]);
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

    public function process(ProcessBoqRequest $request, Boq $boq, \App\Services\BoqExtractionService $extractor): JsonResponse
    {
        set_time_limit(300);
        // Finish even if the phone loses its connection, so the items are not lost.
        ignore_user_abort(true);
        // Spreadsheets are read directly; PDFs and photos are extracted with the AI provider.
        $result = $extractor->extract($boq);
        $created = $result['count'];
        $boq->update(['status' => 'under_review']);

        return (new BoqResource($this->loadPhaseFourRelations($boq->fresh() ?? $boq)))
            ->additional(['success' => true, 'meta' => ['items_imported' => $created, 'warnings' => $result['warnings'], 'provider' => $result['provider']]])
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
        // Detect the real format and convert it to a standard one (.xlsx, .csv, .pdf, .jpg/.png).
        $stored = app(\App\Services\BoqUploadNormalizer::class)->store($file, "boqs/{$project->id}");

        $boq = Boq::create([
            'project_id' => $project->id,
            'organisation_id' => $project->organisation_id,
            'name' => ($validated['name'] ?? null) ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'currency' => $project->currency ?: \App\Support\Regional::currency(),
            'status' => 'uploaded',
            'source_type' => $stored['source_type'],
            'source_file_path' => $stored['path'],
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
