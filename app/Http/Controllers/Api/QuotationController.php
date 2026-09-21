<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Services\QuotationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class QuotationController extends Controller
{
    public function __construct(private readonly QuotationService $quotations)
    {
    }

    /**
     * List quotations visible to the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'status' => ['nullable', 'in:draft,sent,received,reviewed,accepted,rejected,expired'],
            'supplier_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Quotation::query()
            ->with('supplier')
            ->where(fn ($q) => $q
                ->where('created_by', $user->id)
                ->orWhereHas('project', fn ($p) => $p
                    ->where('user_id', $user->id)
                    ->when($user->organisation_id, fn ($o) => $o->orWhere('organisation_id', $user->organisation_id))));

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['supplier_id'])) {
            $query->where('supplier_id', $validated['supplier_id']);
        }
        if (! empty($validated['project_id'])) {
            $query->where('project_id', $validated['project_id']);
        }

        $quotations = $query->latest()->paginate($validated['per_page'] ?? 20);

        return response()->json(['success' => true, 'data' => $quotations]);
    }

    /**
     * Show a quotation with its lines.
     */
    public function show(Quotation $quotation): JsonResponse
    {
        $this->authorizeQuotation($quotation);

        return response()->json([
            'success' => true,
            'data' => $quotation->load(['supplier', 'project', 'boq', 'items.matchedRate.supplier']),
        ]);
    }

    /**
     * Create or update a quotation (and its lines) from extracted or manual data.
     */
    public function store(Request $request, ?Quotation $quotation = null): JsonResponse
    {
        if ($quotation) {
            $this->authorizeQuotation($quotation);
        }

        $validated = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'boq_id' => ['nullable', 'integer', 'exists:boqs,id'],
            'status' => ['sometimes', 'in:draft,sent,received'],
            'quotation_date' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['sometimes', 'numeric', 'min:0'],
            'source' => ['sometimes', 'in:pdf,excel,scanned,camera,manual,api'],
            'source_file_name' => ['nullable', 'string', 'max:255'],
            'source_language' => ['nullable', 'string', 'max:5'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.quantity' => ['sometimes', 'numeric', 'min:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.vat_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'items.*.boq_item_id' => ['nullable', 'integer'],
            'items.*.source_item_code' => ['nullable', 'string', 'max:255'],
        ]);

        $quotation ??= new Quotation();
        $quotation->fill([
            'quote_number' => $quotation->exists ? $quotation->quote_number : $this->newQuoteNumber(),
            'supplier_id' => $validated['supplier_id'],
            'project_id' => $validated['project_id'] ?? null,
            'boq_id' => $validated['boq_id'] ?? null,
            'status' => $validated['status'] ?? 'draft',
            'quotation_date' => $validated['quotation_date'] ?? now()->toDateString(),
            'valid_until' => $validated['valid_until'] ?? null,
            'currency' => $validated['currency'] ?? 'UGX',
            'tax_rate' => $validated['tax_rate'] ?? 0,
            'discount_amount' => $validated['discount_amount'] ?? 0,
            'source' => $validated['source'] ?? 'manual',
            'source_file_name' => $validated['source_file_name'] ?? null,
            'source_language' => $validated['source_language'] ?? 'en',
            'notes' => $validated['notes'] ?? null,
        ]);

        try {
            $saved = $this->quotations->store($quotation, $validated['items'], $request->user());
            $this->audit($request, 'quotation.saved', $saved, null, $saved->fresh()->toArray(), $saved->quote_number);

            return response()->json([
                'success' => true,
                'message' => $quotation->exists ? 'Quotation updated.' : 'Quotation created.',
                'data' => $saved->load(['supplier', 'items']),
            ], 201);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'error_code' => 'QUOTATION_SAVE_FAILED',
                'message' => 'The quotation could not be saved.',
            ], 422);
        }
    }

    /**
     * Mark a quotation as reviewed (AI extraction completed / checked).
     */
    public function review(Request $request, Quotation $quotation): JsonResponse
    {
        $this->authorizeQuotation($quotation);
        $reviewed = $this->quotations->review($quotation, $request->user());
        $this->audit($request, 'quotation.reviewed', $quotation, null, $reviewed->toArray(), $quotation->quote_number);

        return response()->json(['success' => true, 'data' => $reviewed->load(['supplier', 'items'])]);
    }

    /**
     * Toggle approval on a quotation line.
     */
    public function approveLine(Request $request, Quotation $quotation, QuotationItem $item): JsonResponse
    {
        $this->authorizeQuotation($quotation);
        abort_unless($item->quotation_id === $quotation->id, 422, 'Line does not belong to this quotation.');

        $approved = (bool) $request->input('approved', false);
        $item = $this->quotations->setLineApproved($item, $approved);
        $this->quotations->recalculate($quotation);

        $this->audit($request, 'quotation.line_approved', $item, null, $item->toArray(), $quotation->quote_number);

        return response()->json(['success' => true, 'data' => $item->fresh()]);
    }

    /**
     * Accept a quotation; approved lines promote rates to the library.
     */
    public function accept(Request $request, Quotation $quotation): JsonResponse
    {
        $this->authorizeQuotation($quotation);
        $accepted = $this->quotations->accept($quotation, $request->user());
        $this->audit($request, 'quotation.accepted', $quotation, null, $accepted->toArray(), $quotation->quote_number);

        return response()->json([
            'success' => true,
            'message' => 'Quotation accepted. Approved lines were promoted to the rate library.',
            'data' => $accepted->load(['supplier', 'items.matchedRate.supplier']),
        ]);
    }

    /**
     * Reject a quotation.
     */
    public function reject(Request $request, Quotation $quotation): JsonResponse
    {
        $this->authorizeQuotation($quotation);
        $reason = $request->input('reason');
        $rejected = $this->quotations->reject($quotation, $request->user(), $reason);
        $this->audit($request, 'quotation.rejected', $quotation, null, $rejected->toArray(), $quotation->quote_number);

        return response()->json(['success' => true, 'data' => $rejected->load('supplier')]);
    }

    private function authorizeQuotation(Quotation $quotation): void
    {
        $user = request()->user();
        abort_unless(
            $quotation->created_by === $user->id
                || ($quotation->project && $quotation->project->user_id === $user->id)
                || ($user->organisation_id && $quotation->project && $quotation->project->organisation_id === $user->organisation_id),
            403
        );
    }

    private function newQuoteNumber(): string
    {
        return 'QTN-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
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