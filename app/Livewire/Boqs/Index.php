<?php

namespace App\Livewire\Boqs;

use App\Models\Boq;
use App\Models\Project;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public ?int $projectId = null;
    public int $perPage = 15;
    public string $sortBy = 'created_at';
    public string $sortDir = 'desc';
    public array $perPageOptions = [10, 15, 25, 50, 100];

    private const SORTABLE = ['name', 'project.name', 'status', 'currency', 'version', 'items_count', 'created_at'];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedProjectId(): void
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

    public function render()
    {
        $user = auth()->user();

        $boqs = Boq::query()
            ->where(function ($q) use ($user) {
                $q->whereHas('project', fn ($p) => $p->where('user_id', $user->id));

                if ($user->organisation_id !== null) {
                    $q->orWhere('organisation_id', $user->organisation_id);
                }
            })
            ->with('project')
            ->withCount('items')
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->projectId, fn ($q) => $q->where('project_id', $this->projectId));

        match ($this->sortBy) {
            'project.name' => $boqs->orderBy(
                Project::select('name')->whereColumn('projects.id', 'boqs.project_id'),
                $this->sortDir
            ),
            default => $boqs->orderBy($this->sortBy, $this->sortDir),
        };

        $boqs = $boqs->paginate($this->perPage);

        $projects = Project::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);

                if ($user->organisation_id !== null) {
                    $q->orWhere('organisation_id', $user->organisation_id);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $stats = [
            'total_boqs' => Boq::where(function ($q) use ($user) {
                $q->whereHas('project', fn ($p) => $p->where('user_id', $user->id));

                if ($user->organisation_id !== null) {
                    $q->orWhere('organisation_id', $user->organisation_id);
                }
            })->count(),
            'uploaded' => Boq::where(function ($q) use ($user) {
                $q->whereHas('project', fn ($p) => $p->where('user_id', $user->id));

                if ($user->organisation_id !== null) {
                    $q->orWhere('organisation_id', $user->organisation_id);
                }
            })->where('status', 'uploaded')->count(),
            'under_review' => Boq::where(function ($q) use ($user) {
                $q->whereHas('project', fn ($p) => $p->where('user_id', $user->id));

                if ($user->organisation_id !== null) {
                    $q->orWhere('organisation_id', $user->organisation_id);
                }
            })->where('status', 'under_review')->count(),
            'approved' => Boq::where(function ($q) use ($user) {
                $q->whereHas('project', fn ($p) => $p->where('user_id', $user->id));

                if ($user->organisation_id !== null) {
                    $q->orWhere('organisation_id', $user->organisation_id);
                }
            })->where('status', 'approved')->count(),
        ];

        return view('livewire.boqs.index', [
            'boqs' => $boqs,
            'projects' => $projects,
            'stats' => $stats,
        ]);
    }
}