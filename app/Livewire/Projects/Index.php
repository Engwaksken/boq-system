<?php

namespace App\Livewire\Projects;

use App\Models\Project;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public int $perPage = 15;
    public string $sortBy = 'created_at';
    public string $sortDir = 'desc';
    public array $perPageOptions = [10, 15, 25, 50, 100];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortBy === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDir = 'asc';
        }
    }

    public function delete(int $id): void
    {
        $user = auth()->user();
        $project = Project::findOrFail($id);

        abort_unless(
            $project->user_id === $user->id || $project->organisation_id === $user->organisation_id,
            403
        );

        $project->delete();

        session()->flash('status', 'Project deleted.');
    }

    public function render()
    {
        $user = auth()->user();

        $projects = Project::query()
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('organisation_id', $user->organisation_id))
            ->when($this->search !== '', fn ($q) => $q->where(function ($w) {
                $w->where('name', 'like', "%{$this->search}%")
                    ->orWhere('code', 'like', "%{$this->search}%")
                    ->orWhere('client', 'like', "%{$this->search}%")
                    ->orWhere('location', 'like', "%{$this->search}%");
            }))
            ->withCount('boqs')
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate($this->perPage);

        $stats = [
            'total_projects' => Project::where(fn ($q) => $q->where('user_id', $user->id)->orWhere('organisation_id', $user->organisation_id))->count(),
            'active_projects' => Project::where(fn ($q) => $q->where('user_id', $user->id)->orWhere('organisation_id', $user->organisation_id))->where('status', 'active')->count(),
            'total_boqs' => Project::where(fn ($q) => $q->where('user_id', $user->id)->orWhere('organisation_id', $user->organisation_id))->withCount('boqs')->get()->sum('boqs_count'),
            'total_value' => Project::where(fn ($q) => $q->where('user_id', $user->id)->orWhere('organisation_id', $user->organisation_id))->sum('contract_value'),
        ];

        return view('livewire.projects.index', [
            'projects' => $projects,
            'stats' => $stats,
        ]);
    }
}