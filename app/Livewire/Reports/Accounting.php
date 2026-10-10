<?php

namespace App\Livewire\Reports;

use App\Models\Project;
use App\Services\ProjectAccountingService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Accounting extends Component
{
    use \App\Livewire\Concerns\ExportsTables;
    use WithPagination;

    #[Url(as: 'project', except: '')]
    public string $projectFilter = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'period', except: '')]
    public string $periodFilter = '';

    public int $perPage = 15;

    public function boot(): void
    {
        abort_unless(auth()->user()?->hasPermission('reports.view'), 403);
    }

    public function canExportTables(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->hasPermission('reports.export') || $user?->hasPermission('reports.view'));
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPeriodFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    /** Projects the signed-in user is assigned to (used for the project picker). */
    private function projects()
    {
        return $this->baseProjects()->orderBy('name')->get(['id', 'name', 'code']);
    }

    private function baseProjects()
    {
        return Project::where('organisation_id', auth()->user()->organisation_id)
            ->whereHas('assignments', fn ($query) => $query->where('user_id', auth()->id())->whereNull('deleted_at'));
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} expenditure period [from, to] */
    private function periodRange(): array
    {
        return match ($this->periodFilter) {
            'this_month' => [now()->startOfMonth(), null],
            'last_month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'this_quarter' => [now()->startOfQuarter(), null],
            'this_year' => [now()->startOfYear(), null],
            'last_30_days' => [now()->subDays(30)->startOfDay(), null],
            default => [null, null],
        };
    }

    public function render()
    {
        [$from, $to] = $this->periodRange();
        $service = app(ProjectAccountingService::class);

        $projects = $this->projects();
        $selected = $this->projectFilter !== '' ? $projects->firstWhere('id', (int) $this->projectFilter) : null;

        $portfolio = $this->baseProjects()
            ->when(trim($this->search) !== '', fn ($query) => $query->where(fn ($search) => $search
                ->where('name', 'like', '%'.trim($this->search).'%')
                ->orWhere('code', 'like', '%'.trim($this->search).'%')
                ->orWhere('client', 'like', '%'.trim($this->search).'%')))
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->orderBy('name')
            ->paginate($this->exportPageSize($this->perPage))
            ->through(fn (Project $project) => $service->projectTotals($project, $from, $to));

        return view('livewire.reports.accounting', [
            'projects' => $projects,
            'portfolio' => $portfolio,
            'selectedProject' => $selected,
            'breakdown' => $selected ? $service->breakdown($selected, $from, $to) : null,
        ]);
    }
}
