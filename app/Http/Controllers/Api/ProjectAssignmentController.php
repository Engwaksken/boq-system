<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Organisation;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectAssignmentController extends Controller
{
    private function organisationId(Request $request): int
    {
        abort_if($request->user()->organisation_id === null, 403);
        $organisation = Organisation::findOrFail($request->user()->organisation_id);
        $this->authorize('create', [Invitation::class, $organisation]);

        return $organisation->id;
    }

    public function index(Request $request)
    {
        $organisationId = $this->organisationId($request);
        $assignments = ProjectAssignment::whereHas('project', fn ($q) => $q->where('organisation_id', $organisationId))
            ->whereHas('user', fn ($q) => $q->where('organisation_id', $organisationId))
            ->with(['project:id,name', 'user:id,name,email'])->latest('id')->paginate(15);

        return response()->json(['data' => $assignments->items(), 'meta' => [
            'current_page' => $assignments->currentPage(), 'last_page' => $assignments->lastPage(),
        ]]);
    }

    public function options(Request $request)
    {
        $organisationId = $this->organisationId($request);

        return response()->json(['data' => [
            'projects' => Project::where('organisation_id', $organisationId)->orderBy('name')->get(['id', 'name']),
            'members' => User::where('organisation_id', $organisationId)->orderBy('name')->get(['id', 'name', 'email']),
            'roles' => ['project-manager', 'procurement-officer', 'finance', 'user'],
        ]]);
    }

    public function store(Request $request)
    {
        $organisationId = $this->organisationId($request);
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'user_id' => ['required', 'integer'],
            'role' => ['required', 'in:project-manager,procurement-officer,finance,user'],
        ]);
        $assignment = DB::transaction(function () use ($request, $organisationId, $data) {
            $project = Project::where('organisation_id', $organisationId)->lockForUpdate()->findOrFail($data['project_id']);
            $member = User::where('organisation_id', $organisationId)->lockForUpdate()->findOrFail($data['user_id']);
            $assignment = $project->assignments()->withTrashed()->firstOrNew(['user_id' => $member->id]);
            $assignment->fill(['role' => $data['role'], 'assigned_by' => $request->user()->id]);
            $assignment->deleted_at = null;
            $assignment->save();

            return $assignment;
        });

        return response()->json(['data' => $assignment], 201);
    }

    public function destroy(Request $request, int $assignment)
    {
        $organisationId = $this->organisationId($request);
        ProjectAssignment::whereHas('project', fn ($q) => $q->where('organisation_id', $organisationId))
            ->findOrFail($assignment)->delete();

        return response()->noContent();
    }
}
