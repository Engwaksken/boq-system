<?php

namespace App\Livewire;

use App\Models\Boq;
use App\Models\HardwarePrice;
use App\Models\Project;
use App\Services\SubscriptionService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    use \App\Livewire\Concerns\ExportsTables;

    protected function exportViewData(): array
    {
        return ['summary' => [
            ['metric' => __('Projects'), 'value' => $this->projectsCount],
            ['metric' => __('BOQs'), 'value' => $this->boqsCount],
            ['metric' => __('Hardware prices'), 'value' => $this->hardwarePricesCount],
        ], 'projects' => Project::accessibleTo(auth()->user())->latest()->get()];
    }
    public int $projectsCount = 0;

    public int $boqsCount = 0;

    public int $hardwarePricesCount = 0;

    public $subscription = null;

    public $recentProjects;

    public $attentionProjects;

    public function mount(): void
    {
        $user = auth()->user();
        $organisationId = $user?->organisation_id;

        // Members only see projects they are assigned to; administrators see the
        // whole organisation (Project::accessibleTo encodes that boundary).
        $this->projectsCount = Project::accessibleTo($user)->count();

        $this->boqsCount = Boq::query()
            ->whereHas('project', fn ($project) => $project->accessibleTo($user))
            ->count();

        $this->hardwarePricesCount = HardwarePrice::query()
            ->visibleTo($organisationId)
            ->where('is_active', true)
            ->count();

        $this->subscription = app(SubscriptionService::class)
            ->currentSubscription($user, $organisationId);

        $this->recentProjects = Project::accessibleTo($user)
            ->withCount('boqs')
            ->latest()
            ->limit(5)
            ->get(['id', 'name', 'code', 'status', 'progress', 'start_date', 'expected_completion_date', 'contract_value', 'currency']);

        // Projects due within 14 days (or already overdue) so they are not lost track of.
        $this->attentionProjects = Project::accessibleTo($user)
            ->whereIn('status', ['draft', 'active'])
            ->whereNotNull('expected_completion_date')
            ->where('expected_completion_date', '<=', now()->addDays(14)->toDateString())
            ->orderBy('expected_completion_date')
            ->limit(5)
            ->get(['id', 'name', 'code', 'status', 'progress', 'expected_completion_date']);
    }

    public function render()
    {
        return view('livewire.dashboard');
    }
}
