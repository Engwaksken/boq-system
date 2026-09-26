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

    private const SORTABLE = ['name', 'code', 'client', 'location', 'contract_value', 'status', 'start_date', 'created_at'];

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
        if (! in_array($field, self::SORTABLE, true)) {
            return;
        }

        if ($this->sortBy === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDir = 'asc';
        }

        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $user = auth()->user();
        $project = Project::findOrFail($id);

        $personalAccess = $project->user_id === $user->id;
        $organisationAccess = $user->organisation_id !== null
            && $project->organisation_id === $user->organisation_id;

        abort_unless($personalAccess || $organisationAccess, 403);

        $project->delete();

        session()->flash('status', 'Project deleted.');
    }

    public function render()
    {
        $user = auth()->user();

        $projects = Project::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);

                if ($user->organisation_id !== null) {
                    $q->orWhere('organisation_id', $user->organisation_id);
                }
            })
            ->when($this->search !== '', fn ($q) => $q->where(function ($w) {
                $w->where('name', 'like', "%{$this->search}%")
                    ->orWhere('code', 'like', "%{$this->search}%")
                    ->orWhere('client', 'like', "%{$this->search}%")
                    ->orWhere('location', 'like', "%{$this->search}%");
            }))
            ->withCount('boqs')
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate($this->perPage);

        $baseProjectQuery = Project::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);

                if ($user->organisation_id !== null) {
                    $q->orWhere('organisation_id', $user->organisation_id);
                }
            });

        $stats = [
            'total_projects' => (clone $baseProjectQuery)->count(),
            'active_projects' => (clone $baseProjectQuery)->where('status', 'active')->count(),
            'total_boqs' => (clone $baseProjectQuery)->withCount('boqs')->get()->sum('boqs_count'),
            'total_value' => (clone $baseProjectQuery)->sum('contract_value'),
        ];

        return view('livewire.projects.index', [
            'projects' => $projects,
            'stats' => $stats,
        ]);
    }
}