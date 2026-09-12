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

    public function updatedSearch(): void
    {
        $this->resetPage();
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
            ->latest()
            ->paginate(15);

        return view('livewire.projects.index', ['projects' => $projects]);
    }
}