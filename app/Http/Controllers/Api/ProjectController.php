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
        $canViewBoqs = $user->hasPermission('boq.view');

        $projects = Project::query()
            ->accessibleTo($user)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('boq_status'), fn ($q) => $q->whereHas(
                'boqs',
                fn ($boqs) => $boqs->where('status', $request->string('boq_status')->toString())
                    ->where('organisation_id', $user->organisation_id)
                    ->when(! $canViewBoqs, fn ($q) => $q->whereRaw('1 = 0')),
            ))
            ->withCount(['boqs' => fn ($boqs) => $boqs->where('organisation_id', $user->organisation_id)
                ->when(! $canViewBoqs, fn ($q) => $q->whereRaw('1 = 0'))])
            ->latest()
            ->paginate(min(max((int) $request->integer('per_page', 15), 1), 100));

        $projectIds = $projects->getCollection()->pluck('id')->all();
        $visibleBoqIds = $canViewBoqs ? \App\Models\Boq::whereIn('project_id', $projectIds)
            ->where('organisation_id', $user->organisation_id)->pluck('id')->all() : [];
        $totals = app(\App\Services\BoqTotals::class)->forProjects($projectIds, $visibleBoqIds);
        $projects->getCollection()->each(fn (Project $project) => $project->setAttribute('totals', $totals[$project->id]));

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

        $project = Project::createForUser($user, array_merge($validated, ['status' => 'draft']));

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

        $project->load(['boqs' => fn ($boqs) => $boqs->where('organisation_id', $project->organisation_id)]);
        if (! $request->user()->hasPermission('boq.view')) {
            $project->setRelation('boqs', collect());
        }
        $service = app(\App\Services\BoqTotals::class);
        $boqTotals = $service->forBoqs($project->boqs->pluck('id')->all());
        $project->boqs->each(fn ($boq) => $boq->setAttribute('totals', $boqTotals[$boq->id]));
        $project->setAttribute('totals', $service->forProjects([$project->id], $project->boqs->pluck('id')->all())[$project->id]);

        return response()->json([
            'success' => true,
            'data' => $project,
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
     * Use the same tenant and active-assignment boundary as BOQ authorization.
     */
    private function authorizeProjectAccess(Request $request, Project $project): void
    {
        $user = $request->user();

        if (! $project->isAccessibleTo($user)) {
            abort(response()->json([
                'success' => false,
                'error_code' => 'FORBIDDEN',
                'message' => __('auth.forbidden'),
            ], 403));
        }
    }
}
