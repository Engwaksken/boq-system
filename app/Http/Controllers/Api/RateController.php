<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BoqItem;
use App\Models\Rate;
use App\Services\RateLibraryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class RateController extends Controller
{
    public function __construct(private readonly RateLibraryService $rates)
    {
    }

    /**
     * Search the rate library.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:200'],
            'unit' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'currency' => ['nullable', 'string', 'size:3'],
            'supplier_id' => ['nullable', 'integer'],
            'verification_status' => ['nullable', 'in:draft,pending,approved,rejected,expired'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Rate::query()
            ->with('supplier')
            ->when($validated['verification_status'] ?? null, function ($q) use ($validated) {
                $q->where('verification_status', $validated['verification_status']);
            }, fn ($q) => $q->where('verification_status', 'approved')->where('is_active', true));

        $term = trim((string) ($validated['query'] ?? ''));
        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($q) use ($like) {
                foreach (['item', 'description', 'code', 'category'] as $column) {
                    $q->orWhere($column, 'like', $like);
                }
            });
        }

        foreach (['unit', 'category', 'region', 'currency', 'supplier_id'] as $field) {
            if (! empty($validated[$field])) {
                $query->where($field, $validated[$field]);
            }
        }

        $rates = $query
            ->orderByDesc('verified_at')
            ->paginate($validated['per_page'] ?? 20);

        return response()->json(['success' => true, 'data' => $rates]);
    }

    /**
     * Show a single rate.
     */
    public function show(Rate $rate): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $rate->load('supplier', 'verifiedBy', 'createdBy'),
        ]);
    }

    /**
     * Suggest library rates for a BOQ item.
     */
    public function suggestions(BoqItem $boqItem, Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $boqItem->boq && ($boqItem->boq->user_id === $user->id
                || ($user->organisation_id && $boqItem->boq->organisation_id === $user->organisation_id)),
            403
        );

        $suggestions = $this->rates->suggestionsForItem($boqItem, $request->input('currency'));

        return response()->json([
            'success' => true,
            'data' => array_map(fn ($row) => [
                'score' => $row['score'],
                'rate' => $row['rate']->load('supplier'),
            ], $suggestions),
        ]);
    }

    /**
     * Create a rate entry.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:50'],
            'rate' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'region' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'size:2'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'source_type' => ['required', 'in:previous_boq,supplier_quotation,supplier_price_list,procurement,market_survey,reference_schedule,external_feed,manual'],
            'source_reference' => ['nullable', 'string', 'max:255'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date'],
            'verification_status' => ['required', 'in:draft,pending,approved'],
            'original_language' => ['nullable', 'string', 'max:5'],
        ]);

        $user = $request->user();
        $isVerifying = in_array($validated['verification_status'], ['approved'], true);

        try {
            $rate = Rate::create([
                'code' => 'RATE-'.Str::upper(Str::slug($validated['item']).'-'.Str::random(4)),
                'item' => $validated['item'],
                'description' => $validated['description'] ?? null,
                'category' => $validated['category'] ?? null,
                'unit' => $validated['unit'],
                'rate' => $validated['rate'],
                'currency' => $validated['currency'],
                'region' => $validated['region'] ?? null,
                'country' => $validated['country'] ?? null,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'source_type' => $validated['source_type'],
                'source_reference' => $validated['source_reference'] ?? null,
                'effective_from' => $validated['effective_from'] ?? now()->toDateString(),
                'effective_until' => $validated['effective_until'] ?? null,
                'verification_status' => $validated['verification_status'],
                'verified_by' => $isVerifying ? $user->id : null,
                'verified_at' => $isVerifying ? now() : null,
                'is_active' => $isVerifying,
                'original_language' => $validated['original_language'] ?? 'en',
                'created_by' => $user->id,
            ]);

            $this->audit($request, 'rate.created', $rate, null, $rate->fresh()->toArray(), $rate->code);

            return response()->json([
                'success' => true,
                'message' => 'Rate saved.',
                'data' => $rate->load('supplier'),
            ], 201);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'error_code' => 'RATE_CREATION_FAILED',
                'message' => 'The rate could not be saved.',
            ], 422);
        }
    }

    /**
     * Update a rate entry.
     */
    public function update(Request $request, Rate $rate): JsonResponse
    {
        $validated = $request->validate([
            'item' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'unit' => ['sometimes', 'string', 'max:50'],
            'rate' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'region' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'size:2'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'source_type' => ['sometimes', 'in:previous_boq,supplier_quotation,supplier_price_list,procurement,market_survey,reference_schedule,external_feed,manual'],
            'source_reference' => ['nullable', 'string', 'max:255'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date'],
        ]);

        $rate->update($validated);
        $this->audit($request, 'rate.updated', $rate, null, $rate->fresh()->toArray(), $rate->code);

        return response()->json(['success' => true, 'data' => $rate->fresh()->load('supplier')]);
    }

    /**
     * Approve a rate.
     */
    public function approve(Request $request, Rate $rate): JsonResponse
    {
        $approved = $this->rates->approve($rate, $request->user());
        $this->audit($request, 'rate.approved', $rate, null, $approved->toArray(), $rate->code);

        return response()->json(['success' => true, 'data' => $approved->load('supplier')]);
    }

    /**
     * Reject a rate.
     */
    public function reject(Request $request, Rate $rate): JsonResponse
    {
        $reason = $request->input('reason');
        $rejected = $this->rates->reject($rate, $request->user(), $reason);
        $this->audit($request, 'rate.rejected', $rate, null, $rejected->toArray(), $rate->code);

        return response()->json(['success' => true, 'data' => $rejected->load('supplier')]);
    }

    private function audit(Request $request, string $action, object $entity, ?array $previous, ?array $new, ?string $reference = null): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'organisation_id' => $request->user()?->organisation_id,
            'action' => $action,
            'entity_type' => $entity::class,
            'entity_id' => $entity->id ?? null,
            'previous_value' => $previous,
            'new_value' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'locale' => $request->user()?->locale,
            'reference' => $reference,
        ]);
    }
}