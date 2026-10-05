<?php

namespace App\Livewire\Team;

use App\Models\Invitation;
use App\Models\Organisation;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Assignments extends Component
{
    use WithPagination;

    public ?int $projectId = null;
    public ?int $userId = null;
    public string $role = 'user';

    public function boot(): void
    {
        abort_unless(auth()->user()?->hasVerifiedEmail(), 403);
        $this->authorize('create', [Invitation::class, Organisation::findOrFail(auth()->user()->organisation_id)]);
    }

    public function save(): void
    {
        $this->validate([
            'projectId' => ['required', 'integer'], 'userId' => ['required', 'integer'],
            'role' => ['required', 'in:project-manager,procurement-officer,finance,user'],
        ]);
        DB::transaction(function () {
            $project = Project::where('organisation_id', auth()->user()->organisation_id)->lockForUpdate()->find($this->projectId);
            $member = User::where('organisation_id', auth()->user()->organisation_id)->lockForUpdate()->find($this->userId);
            abort_unless($project && $member, 404);
            $assignment = $project->assignments()->withTrashed()->firstOrNew(['user_id' => $member->id]);
            $assignment->fill(['role' => $this->role, 'assigned_by' => auth()->id()]);
            $assignment->deleted_at = null;
            $assignment->save();
        });
        $this->reset(['projectId', 'userId', 'role']);
        session()->flash('status', __('Project assignment saved.'));
    }

    public function revoke(int $id): void
    {
        $assignment = ProjectAssignment::whereHas('project', fn ($query) => $query->where('organisation_id', auth()->user()->organisation_id))->findOrFail($id);
        $assignment->delete();
        session()->flash('status', __('Project assignment revoked.'));
    }

    public function render()
    {
        $organisationId = auth()->user()->organisation_id;

        return view('livewire.team.assignments', [
            'projects' => Project::where('organisation_id', $organisationId)->orderBy('name')->get(),
            'members' => User::where('organisation_id', $organisationId)->orderBy('name')->get(['id', 'name', 'email']),
            'assignments' => ProjectAssignment::whereHas('project', fn ($query) => $query->where('organisation_id', $organisationId))
                ->whereHas('user', fn ($query) => $query->where('organisation_id', $organisationId))
                ->with(['project', 'user'])->latest()->paginate(15),
        ]);
    }
}
