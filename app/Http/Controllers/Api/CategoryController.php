<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Categories;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Selectable categories for forms and filters in the apps.
 */
class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'project_types' => Categories::projectTypes(),
                'work_sections' => Categories::workSections(),
                'materials' => Categories::materials($request->user()->organisation_id),
            ],
        ]);
    }
}
