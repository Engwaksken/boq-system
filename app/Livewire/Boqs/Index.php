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
        if ($this->sortBy === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDir = 'asc';
        }
    }

    public function render()
    {
        $user = auth()->user();

        $boqs = Boq::query()
            ->where(function ($q) use ($user) {
                $q->whereHas('project', fn ($p) => $p->where('user_id', $user->id))
                    ->orWhere('organisation_id', $user->organisation_id);
            })
            ->with('project')
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->projectId, fn ($q) => $q->where('project_id', $this->projectId))
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate($this->perPage);

        $projects = Project::query()
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('organisation_id', $user->organisation_id))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $stats = [
            'total_boqs' => Boq::where(function ($q) use ($user) {
                $q->whereHas('project', fn ($p) => $p->where('user_id', $user->id))
                    ->orWhere('organisation_id', $user->organisation_id);
            })->count(),
            'uploaded' => Boq::where(function ($q) use ($user) {
                $q->whereHas('project', fn ($p) => $p->where('user_id', $user->id))
                    ->orWhere('organisation_id', $user->organisation_id);
            })->where('status', 'uploaded')->count(),
            'under_review' => Boq::where(function ($q) use ($user) {
                $q->whereHas('project', fn ($p) => $p->where('user_id', $user->id))
                    ->orWhere('organisation_id', $user->organisation_id);
            })->where('status', 'under_review')->count(),
            'approved' => Boq::where(function ($q) use ($user) {
                $q->whereHas('project', fn ($p) => $p->where('user_id', $user->id))
                    ->orWhere('organisation_id', $user->organisation_id);
            })->where('status', 'approved')->count(),
        ];

        return view('livewire.boqs.index', [
            'boqs' => $boqs,
            'projects' => $projects,
            'stats' => $stats,
        ]);
    }
}