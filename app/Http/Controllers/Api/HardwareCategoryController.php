<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHardwareCategoryRequest;
use App\Http\Requests\UpdateHardwareCategoryRequest;
use App\Http\Resources\HardwareCategoryResource;
use App\Models\HardwareCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HardwareCategoryController extends Controller
{
    /**
     * List hardware categories with pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HardwareCategory::class);

        $query = HardwareCategory::query()
            ->withCount('hardwarePrices')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('description', 'like', "%{$request->search}%");
            });
        }

        $perPage = min($request->get('per_page', 20), 100);
        $categories = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => HardwareCategoryResource::collection($categories),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
            ],
        ]);
    }

    /**
     * Create a new hardware category.
     */
    public function store(StoreHardwareCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', HardwareCategory::class);

        $validated = $request->validated();
        $validated['created_by'] = $request->user()->id;
        $validated['updated_by'] = $request->user()->id;

        $category = HardwareCategory::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Hardware category created successfully.',
            'data' => new HardwareCategoryResource($category),
        ], 201);
    }

    /**
     * Show category details.
     */
    public function show(Request $request, HardwareCategory $category): JsonResponse
    {
        $this->authorize('view', $category);

        $category->loadCount('hardwarePrices');

        return response()->json([
            'success' => true,
            'data' => new HardwareCategoryResource($category),
        ]);
    }

    /**
     * Update category.
     */
    public function update(UpdateHardwareCategoryRequest $request, HardwareCategory $category): JsonResponse
    {
        $this->authorize('update', $category);

        $validated = $request->validated();
        $validated['updated_by'] = $request->user()->id;

        $category->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Hardware category updated successfully.',
            'data' => new HardwareCategoryResource($category->fresh()),
        ]);
    }

    /**
     * Delete category.
     */
    public function destroy(Request $request, HardwareCategory $category): JsonResponse
    {
        $this->authorize('delete', $category);

        // Check if category has associated hardware prices
        if ($category->hardwarePrices()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with associated hardware prices. Reassign or delete the prices first.',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Hardware category deleted successfully.',
        ]);
    }

    /**
     * Toggle category active status.
     */
    public function toggleActive(Request $request, HardwareCategory $category): JsonResponse
    {
        $this->authorize('toggleActive', $category);

        $category->update([
            'is_active' => ! $category->is_active,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => $category->is_active ? 'Category activated.' : 'Category deactivated.',
            'data' => new HardwareCategoryResource($category->fresh()),
        ]);
    }
}