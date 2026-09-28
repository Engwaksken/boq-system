<?php

namespace App\Livewire\Projects;

use App\Models\Project;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Project $project;

    public function mount(Project $project): void
    {
        $user = auth()->user();

        abort_unless(
            $project->user_id === $user->id || $project->organisation_id === $user->organisation_id,
            403
        );

        $this->project = $project->load('boqs');
    }

    public function render()
    {
        $service = app(\App\Services\BoqTotals::class);

        return view('livewire.projects.show', [
            'totals' => $service->forProjects([$this->project->id])[$this->project->id],
            'boqTotals' => $service->forBoqs($this->project->boqs->pluck('id')->all()),
        ]);
    }
}