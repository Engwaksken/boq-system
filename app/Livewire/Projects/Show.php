<?php

namespace App\Livewire\Projects;

use App\Models\Project;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    use \App\Livewire\Concerns\ExportsTables;
    public Project $project;

    public function mount(Project $project): void
    {
        $user = auth()->user();

        abort_unless($project->isAccessibleTo($user), 403);

        $this->project = $project;
    }

    public function render()
    {
        $user = auth()->user();
        abort_unless($this->project->isAccessibleTo($user), 403);
        $this->project->load(['boqs' => fn ($query) => $query->where('organisation_id', $this->project->organisation_id)
            ->when(! $user->hasPermission('boq.view'), fn ($query) => $query->whereRaw('1 = 0'))]);
        $service = app(\App\Services\BoqTotals::class);

        return view('livewire.projects.show', [
            'boqs' => $this->project->boqs,
            'totals' => $service->forProjects([$this->project->id], $this->project->boqs->pluck('id')->all())[$this->project->id],
            'boqTotals' => $service->forBoqs($this->project->boqs->pluck('id')->all()),
        ]);
    }
}
