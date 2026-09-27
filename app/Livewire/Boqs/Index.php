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
    use \App\Livewire\Concerns\UsesPreferredPerPage;
    use \App\Livewire\Concerns\WithBulkSelection;
    use WithPagination;

    public ?int $editingBoqId = null;

    public string $editName = '';

    public ?int $editProjectId = null;

    public ?int $deletingBoqId = null;

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

    /**
     * A BOQ the signed-in user may change (owner or same organisation, with boq.edit).
     */
    private function editableBoq(int $id, string $ability = 'update'): Boq
    {
        $boq = Boq::with('project')->findOrFail($id);

        abort_unless(auth()->user()->can($ability, $boq), 403);

        return $boq;
    }

    public function editBoq(int $id): void
    {
        $boq = $this->editableBoq($id);

        $this->editingBoqId = $boq->id;
        $this->editName = (string) $boq->name;
        $this->editProjectId = $boq->project_id;
        $this->resetValidation();
    }

    public function closeEdit(): void
    {
        $this->editingBoqId = null;
        $this->resetValidation();
    }

    public function saveBoq(): void
    {
        $boq = $this->editableBoq((int) $this->editingBoqId);

        $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editProjectId' => ['required', 'integer'],
        ]);

        $user = auth()->user();
        $project = Project::findOrFail($this->editProjectId);

        // Only move a BOQ into a project the user can also access.
        $canUseProject = $user->organisation_id !== null
            ? $project->organisation_id === $user->organisation_id
            : $project->user_id === $user->id && $project->organisation_id === null;

        if (! $canUseProject) {
            $this->addError('editProjectId', __('Choose one of your projects.'));

            return;
        }

        $boq->update([
            'name' => trim($this->editName),
            'project_id' => $project->id,
            'organisation_id' => $project->organisation_id,
        ]);

        session()->flash('status', __('BOQ updated.'));
        $this->closeEdit();
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingBoqId = $this->editableBoq($id, 'delete')->id;
    }

    public function cancelDelete(): void
    {
        $this->deletingBoqId = null;
    }

    public function deleteBoq(): void
    {
        $boq = $this->editableBoq((int) $this->deletingBoqId, 'delete');

        // Soft delete: items and pricing history stay recoverable by an administrator.
        $boq->delete();

        $this->deletingBoqId = null;
        session()->flash('status', __('BOQ deleted.'));
    }

    public function bulkDelete(): void
    {
        $user = auth()->user();

        $count = Boq::with('project')
            ->whereKey($this->selectedIds())
            ->get()
            ->filter(fn (Boq $boq) => $user->can('delete', $boq))
            ->each->delete()
            ->count();

        $this->finishBulkAction($count, 'deleted', 'status');
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