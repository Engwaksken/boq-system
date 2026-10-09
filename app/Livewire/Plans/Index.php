<?php

namespace App\Livewire\Plans;

use App\Models\Plan;
use App\Models\Subscription;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use \App\Livewire\Concerns\ExportsTables;
    use \App\Livewire\Concerns\UsesPreferredPerPage;
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'active';
    public int $perPage = 10;
    public string $sortBy = 'display_order';
    public string $sortDir = 'asc';
    public array $perPageOptions = [10, 20, 50];

    private const SORTABLE = ['name', 'price', 'duration_days', 'is_active', 'display_order'];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
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

    public function render()
    {
        $plans = Plan::query()
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true)->where('is_archived', false))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false)->where('is_archived', false))
            ->when($this->statusFilter === 'archived', fn ($q) => $q->where('is_archived', true))
            ->with('features')
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate($this->exportPageSize($this->perPage));

        $available = Plan::query()->where('is_active', true)->where('is_archived', false);

        $stats = [
            // Shown on the page (these keys were missing, so the cards always read 0).
            'available_plans' => (clone $available)->count(),
            'monthly_plans' => (clone $available)->where('type', 'monthly')->count(),
            'annual_plans' => (clone $available)->where('type', 'annual')->count(),

            'total_plans' => Plan::count(),
            'active_plans' => Plan::where('is_active', true)->where('is_archived', false)->count(),
            'archived_plans' => Plan::where('is_archived', true)->count(),
            'total_subscriptions' => Subscription::whereHas('plan')->count(),
        ];

        $user = auth()->user();

        return view('livewire.plans.index', [
            'plans' => $plans,
            'stats' => $stats,
            'currentPlanId' => $user
                ? app(\App\Services\SubscriptionService::class)->currentSubscription($user, $user->organisation_id)?->plan_id
                : null,
        ]);
    }
}
