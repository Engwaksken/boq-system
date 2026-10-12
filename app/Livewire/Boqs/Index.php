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
    use \App\Livewire\Concerns\ExportsTables;
    use \App\Livewire\Concerns\UsesPreferredPerPage;
    use \App\Livewire\Concerns\WithBulkSelection;
    use WithPagination;

    public ?int $editingBoqId = null;

    public string $editName = '';

    public ?int $editProjectId = null;

    public ?int $deletingBoqId = null;

    public string $search = '';
    public string $statusTab = 'all';
    public ?int $projectId = null;
    public int $perPage = 15;
    #[\Livewire\Attributes\Locked] // only changed via sortBy(), which whitelists columns
    public string $sortBy = 'created_at';
    #[\Livewire\Attributes\Locked] // only changed via sortBy(), which whitelists columns
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

    public function setStatusTab(string $status): void
    {
        if (! in_array($status, ['all', 'uploaded', 'under_review', 'approved'], true)) {
            return;
        }

        $this->statusTab = $status;
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
        if (! $project->isAccessibleTo($user)) {
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
            ->visibleTo($user)
            ->with('project')
            ->withCount('items')
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->projectId, fn ($q) => $q->where('project_id', $this->projectId))
            ->when($this->statusTab !== 'all', fn ($q) => $q->where('status', $this->statusTab));

        match ($this->sortBy) {
            'project.name' => $boqs->orderBy(
                Project::select('name')->whereColumn('projects.id', 'boqs.project_id'),
                $this->sortDir
            ),
            default => $boqs->orderBy($this->sortBy, $this->sortDir),
        };

        $boqs = $boqs->paginate($this->exportPageSize($this->perPage));

        $projects = Project::query()
            ->accessibleTo($user)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $visible = Boq::query()->visibleTo($user);
        $stats = [
            'total_boqs' => (clone $visible)->count(),
            'uploaded' => (clone $visible)->where('status', 'uploaded')->count(),
            'under_review' => (clone $visible)->where('status', 'under_review')->count(),
            'approved' => (clone $visible)->where('status', 'approved')->count(),
        ];

        return view('livewire.boqs.index', [
            'boqs' => $boqs,
            'projects' => $projects,
            'stats' => $stats,
            'totals' => app(\App\Services\BoqTotals::class)->forBoqs($boqs->getCollection()->pluck('id')->all()),
        ]);
    }
}
