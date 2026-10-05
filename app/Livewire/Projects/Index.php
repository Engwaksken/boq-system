<?php

namespace App\Livewire\Projects;

use App\Models\Project;
use App\Livewire\Concerns\WithBulkSelection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use \App\Livewire\Concerns\UsesPreferredPerPage;
    use WithBulkSelection;
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

    public function bulkDelete(): void
    {
        $user = auth()->user();

        abort_unless($user->hasPermission('projects.edit'), 403);
        $count = Project::query()
            ->whereKey($this->selectedIds())
            ->accessibleTo($user)
            ->get()
            ->each->delete()
            ->count();

        $this->finishBulkAction($count, 'deleted', 'status');
    }

    public function delete(int $id): void
    {
        $user = auth()->user();
        $project = Project::findOrFail($id);

        abort_unless($user->hasPermission('projects.edit') && $project->isAccessibleTo($user), 403);

        $project->delete();

        session()->flash('status', 'Project deleted.');
    }

    public function render()
    {
        $user = auth()->user();
        $canViewBoqs = $user->hasPermission('boq.view');
        $boqScope = fn ($query) => $query->where('organisation_id', $user->organisation_id)
            ->when(! $canViewBoqs, fn ($query) => $query->whereRaw('1 = 0'));

        $projects = Project::query()
            ->accessibleTo($user)
            ->when($this->search !== '', fn ($q) => $q->where(function ($w) {
                $w->where('name', 'like', "%{$this->search}%")
                    ->orWhere('code', 'like', "%{$this->search}%")
                    ->orWhere('client', 'like', "%{$this->search}%")
                    ->orWhere('location', 'like', "%{$this->search}%");
            }))
            ->withCount(['boqs' => $boqScope])
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate($this->perPage);

        $projectIds = $projects->getCollection()->pluck('id')->all();
        $visibleBoqIds = $canViewBoqs ? \App\Models\Boq::whereIn('project_id', $projectIds)
            ->where('organisation_id', $user->organisation_id)->pluck('id')->all() : [];
        $totals = app(\App\Services\BoqTotals::class)->forProjects($projectIds, $visibleBoqIds);

        $baseProjectQuery = Project::accessibleTo($user);

        $stats = [
            'total_projects' => (clone $baseProjectQuery)->count(),
            'active_projects' => (clone $baseProjectQuery)->where('status', 'active')->count(),
            'total_boqs' => (clone $baseProjectQuery)->withCount(['boqs' => $boqScope])->get()->sum('boqs_count'),
            'total_value' => (clone $baseProjectQuery)->sum('contract_value'),
        ];

        return view('livewire.projects.index', [
            'projects' => $projects,
            'stats' => $stats,
            'totals' => $totals,
        ]);
    }
}
