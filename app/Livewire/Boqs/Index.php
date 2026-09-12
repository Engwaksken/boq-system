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

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedProjectId(): void
    {
        $this->resetPage();
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
            ->latest()
            ->paginate(15);

        $projects = Project::query()
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('organisation_id', $user->organisation_id))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return view('livewire.boqs.index', [
            'boqs' => $boqs,
            'projects' => $projects,
        ]);
    }
}