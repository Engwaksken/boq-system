<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAiProviderRequest;
use App\Http\Requests\UpdateAiProviderRequest;
use App\Http\Resources\AiProviderResource;
use App\Models\AiProvider;
use App\Services\AiProviderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiProviderController extends Controller
{
    public function __construct(
        private AiProviderService $aiProviderService
    ) {}

    /**
     * List AI providers with pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AiProvider::class);

        $query = AiProvider::query()
            ->with('organisation')
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($request->filled('enabled')) {
            $query->where('is_enabled', $request->boolean('enabled'));
        }

        if ($request->filled('provider_type')) {
            $query->where('provider_type', $request->provider_type);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('key', 'like', "%{$request->search}%");
            });
        }

        $perPage = min($request->get('per_page', 20), 100);
        $providers = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => AiProviderResource::collection($providers),
            'meta' => [
                'current_page' => $providers->currentPage(),
                'last_page' => $providers->lastPage(),
                'per_page' => $providers->perPage(),
                'total' => $providers->total(),
            ],
        ]);
    }

    /**
     * Create a new AI provider with encrypted API key.
     */
    public function store(StoreAiProviderRequest $request): JsonResponse
    {
        $this->authorize('create', AiProvider::class);

        $validated = $request->validated();
        $validated['created_by'] = $request->user()->id;
        $validated['updated_by'] = $request->user()->id;

        // If setting as default, unset other defaults for the same organisation
        if ($validated['is_default'] ?? false) {
            AiProvider::where('organisation_id', $validated['organisation_id'] ?? null)
                ->where('is_default', true)
                ->update(['is_default' => false, 'updated_by' => $request->user()->id]);
        }

        $provider = AiProvider::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'AI provider created successfully.',
            'data' => new AiProviderResource($provider),
        ], 201);
    }

    /**
     * Show provider details (API key is hidden via model's $hidden).
     */
    public function show(Request $request, AiProvider $provider): JsonResponse
    {
        $this->authorize('view', $provider);

        $provider->load('organisation');

        return response()->json([
            'success' => true,
            'data' => new AiProviderResource($provider),
        ]);
    }

    /**
     * Update provider.
     */
    public function update(UpdateAiProviderRequest $request, AiProvider $provider): JsonResponse
    {
        $this->authorize('update', $provider);

        $validated = $request->validated();
        $validated['updated_by'] = $request->user()->id;

        // If setting as default, unset other defaults for the same organisation
        if ($validated['is_default'] ?? false) {
            AiProvider::where('organisation_id', $provider->organisation_id)
                ->where('id', '!=', $provider->id)
                ->where('is_default', true)
                ->update(['is_default' => false, 'updated_by' => $request->user()->id]);
        }

        $provider->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'AI provider updated successfully.',
            'data' => new AiProviderResource($provider->fresh()),
        ]);
    }

    /**
     * Delete provider.
     */
    public function destroy(Request $request, AiProvider $provider): JsonResponse
    {
        $this->authorize('delete', $provider);

        // Prevent deleting the last enabled provider for an organisation
        $enabledCount = AiProvider::where('organisation_id', $provider->organisation_id)
            ->where('is_enabled', true)
            ->count();

        if ($enabledCount <= 1 && $provider->is_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete the last enabled AI provider. Disable it instead or create another enabled provider first.',
            ], 422);
        }

        $provider->delete();

        return response()->json([
            'success' => true,
            'message' => 'AI provider deleted successfully.',
        ]);
    }

    /**
     * Test connection to the AI provider.
     */
    public function test(Request $request, AiProvider $provider): JsonResponse
    {
        $this->authorize('test', $provider);

        $result = $this->aiProviderService->test($provider);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Set provider as default for its organisation.
     */
    public function setDefault(Request $request, AiProvider $provider): JsonResponse
    {
        $this->authorize('setDefault', $provider);

        // Unset other defaults for the same organisation
        AiProvider::where('organisation_id', $provider->organisation_id)
            ->where('id', '!=', $provider->id)
            ->where('is_default', true)
            ->update(['is_default' => false, 'updated_by' => $request->user()->id]);

        $provider->update([
            'is_default' => true,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'AI provider set as default.',
            'data' => new AiProviderResource($provider->fresh()),
        ]);
    }
}