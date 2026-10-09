<?php

namespace App\Livewire\Reports;

use App\Models\Project;
use App\Services\ProjectAccountingService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Accounting extends Component
{
    use \App\Livewire\Concerns\ExportsTables;

    #[Url(as: 'project', except: '')]
    public string $projectFilter = '';

    public function boot(): void
    {
        abort_unless(auth()->user()?->hasPermission('reports.view'), 403);
    }

    public function canExportTables(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->hasPermission('reports.export') || $user?->hasPermission('reports.view'));
    }

    /** Projects the signed-in user is assigned to. */
    private function projects()
    {
        return Project::where('organisation_id', auth()->user()->organisation_id)
            ->whereHas('assignments', fn ($query) => $query->where('user_id', auth()->id())->whereNull('deleted_at'))
            ->orderBy('name')->get();
    }

    public function render()
    {
        $projects = $this->projects();
        $selected = $this->projectFilter !== '' ? $projects->firstWhere('id', (int) $this->projectFilter) : null;
        $service = app(ProjectAccountingService::class);

        return view('livewire.reports.accounting', [
            'projects' => $projects,
            'portfolio' => $service->portfolio(auth()->user()),
            'selectedProject' => $selected,
            'breakdown' => $selected ? $service->breakdown($selected) : null,
        ]);
    }
}
