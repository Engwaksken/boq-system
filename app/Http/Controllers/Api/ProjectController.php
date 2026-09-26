<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    /**
     * List projects for the authenticated user/organisation.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $projects = Project::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);

                if ($user->organisation_id !== null) {
                    $q->orWhere('organisation_id', $user->organisation_id);
                }
            })
            ->withCount('boqs')
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $projects,
        ]);
    }

    /**
     * Store a newly created project.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:255'],
            'client' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contractor' => ['sometimes', 'nullable', 'string', 'max:255'],
            'consultant' => ['sometimes', 'nullable', 'string', 'max:255'],
            'quantity_surveyor' => ['sometimes', 'nullable', 'string', 'max:255'],
            'project_manager' => ['sometimes', 'nullable', 'string', 'max:255'],
            'site_engineer' => ['sometimes', 'nullable', 'string', 'max:255'],
            'funding_organisation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country' => ['sometimes', 'nullable', 'string', 'max:255'],
            'district' => ['sometimes', 'nullable', 'string', 'max:255'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'project_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'expected_completion_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'contract_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'original_language' => ['sometimes', 'string', 'max:10'],
            'report_language' => ['sometimes', 'string', 'max:10'],
            'status' => ['sometimes', 'string', 'in:draft,active,completed,archived'],
        ]);

        $project = Project::create(array_merge($validated, [
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'status' => 'draft',
        ]));

        return response()->json([
            'success' => true,
            'message' => __('projects.created'),
            'data' => $project,
        ], 201);
    }

    /**
     * Display the specified project.
     */
    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProjectAccess($request, $project);

        return response()->json([
            'success' => true,
            'data' => $project->load('boqs'),
        ]);
    }

    /**
     * Update the specified project.
     */
    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProjectAccess($request, $project);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:255'],
            'client' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contractor' => ['sometimes', 'nullable', 'string', 'max:255'],
            'consultant' => ['sometimes', 'nullable', 'string', 'max:255'],
            'quantity_surveyor' => ['sometimes', 'nullable', 'string', 'max:255'],
            'project_manager' => ['sometimes', 'nullable', 'string', 'max:255'],
            'site_engineer' => ['sometimes', 'nullable', 'string', 'max:255'],
            'funding_organisation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country' => ['sometimes', 'nullable', 'string', 'max:255'],
            'district' => ['sometimes', 'nullable', 'string', 'max:255'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'project_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'expected_completion_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'contract_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'original_language' => ['sometimes', 'string', 'max:10'],
            'report_language' => ['sometimes', 'string', 'max:10'],
            'status' => ['sometimes', 'string', 'in:draft,active,completed,archived'],
        ]);

        $project->update($validated);

        return response()->json([
            'success' => true,
            'message' => __('projects.updated'),
            'data' => $project,
        ]);
    }

    /**
     * Remove the specified project.
     */
    public function destroy(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProjectAccess($request, $project);

        $project->delete();

        return response()->json([
            'success' => true,
            'message' => __('projects.deleted'),
        ]);
    }

    /**
     * Ensure the authenticated user owns the project or belongs to its organisation.
     */
    private function authorizeProjectAccess(Request $request, Project $project): void
    {
        $user = $request->user();

        $personalAccess = $project->user_id === $user->id;
        $organisationAccess = $user->organisation_id !== null
            && $project->organisation_id === $user->organisation_id;

        if (! $personalAccess && ! $organisationAccess) {
            abort(response()->json([
                'success' => false,
                'error_code' => 'FORBIDDEN',
                'message' => __('auth.forbidden'),
            ], 403));
        }
    }
}
