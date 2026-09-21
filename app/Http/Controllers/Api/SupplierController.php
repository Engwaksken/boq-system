<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SupplierController extends Controller
{
    /**
     * List suppliers.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:200'],
            'region' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Supplier::query();
        $term = trim((string) ($validated['query'] ?? ''));
        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('contact_name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('code', 'like', $like);
            });
        }

        if (! empty($validated['region'])) {
            $query->where('region', $validated['region']);
        }
        if (array_key_exists('is_active', $validated)) {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $suppliers = $query
            ->withCount('rates')
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 20);

        return response()->json(['success' => true, 'data' => $suppliers]);
    }

    /**
     * Show a single supplier with rates and quotations.
     */
    public function show(Supplier $supplier): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $supplier->load(['rates' => fn ($q) => $q->latest()->limit(20), 'createdBy']),
        ]);
    }

    /**
     * Create a supplier.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'location' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'size:2'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'materials' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
            'preferred_language' => ['nullable', 'string', 'max:5'],
        ]);

        $supplier = Supplier::create([
            'code' => 'SUP-'.Str::upper(Str::random(6)),
            'name' => $validated['name'],
            'contact_name' => $validated['contact_name'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'location' => $validated['location'] ?? null,
            'region' => $validated['region'] ?? null,
            'country' => $validated['country'] ?? null,
            'currency' => $validated['currency'] ?? 'UGX',
            'materials' => $validated['materials'] ?? [],
            'notes' => $validated['notes'] ?? null,
            'preferred_language' => $validated['preferred_language'] ?? 'en',
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        $this->audit($request, 'supplier.created', $supplier, null, $supplier->fresh()->toArray(), $supplier->code);

        return response()->json([
            'success' => true,
            'message' => 'Supplier saved.',
            'data' => $supplier,
        ], 201);
    }

    /**
     * Update a supplier.
     */
    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'location' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'size:2'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'materials' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
            'preferred_language' => ['nullable', 'string', 'max:5'],
            'is_active' => ['sometimes', 'boolean'],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
        ]);

        $supplier->update($validated);
        $this->audit($request, 'supplier.updated', $supplier, null, $supplier->fresh()->toArray(), $supplier->code);

        return response()->json(['success' => true, 'data' => $supplier->fresh()]);
    }

    /**
     * Deactivate a supplier (keeps historical quotations).
     */
    public function destroy(Request $request, Supplier $supplier): JsonResponse
    {
        $supplier->update(['is_active' => false]);
        $this->audit($request, 'supplier.deactivated', $supplier, null, $supplier->fresh()->toArray(), $supplier->code);

        return response()->json([
            'success' => true,
            'message' => 'Supplier deactivated. Historical quotations were preserved.',
        ]);
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