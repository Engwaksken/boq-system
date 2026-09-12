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
    public int $projectsCount = 0;

    public int $boqsCount = 0;

    public int $hardwarePricesCount = 0;

    public $subscription = null;

    public $recentProjects;

    public function mount(): void
    {
        $user = auth()->user();
        $organisationId = $user?->organisation_id;

        $this->projectsCount = Project::query()
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('organisation_id', $organisationId))
            ->count();

        $this->boqsCount = Boq::query()
            ->where(fn ($q) => $q
                ->whereHas('project', fn ($p) => $p->where('user_id', $user->id))
                ->orWhere('organisation_id', $organisationId))
            ->count();

        $this->hardwarePricesCount = HardwarePrice::query()
            ->where('organisation_id', $organisationId)
            ->where('is_active', true)
            ->count();

        $this->subscription = app(SubscriptionService::class)
            ->currentSubscription($user, $organisationId);

        $this->recentProjects = Project::query()
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('organisation_id', $organisationId))
            ->withCount('boqs')
            ->latest()
            ->limit(5)
            ->get(['id', 'name', 'code', 'status', 'contract_value', 'currency']);
    }

    public function render()
    {
        return view('livewire.dashboard');
    }
}