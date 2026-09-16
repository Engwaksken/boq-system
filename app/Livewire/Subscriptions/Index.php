<?php

namespace App\Livewire\Subscriptions;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $activeTab = 'subscriptions';
    public string $search = '';
    public string $statusFilter = 'all';
    public string $periodFilter = 'all';
    public int $perPage = 10;
    public string $planSearch = '';
    public string $planPeriodFilter = 'all';
    public int $planPerPage = 10;
    public array $selectedSubscriptions = [];
    public bool $selectPage = false;
    public bool $showActionModal = false;
    public string $actionType = '';
    public ?int $actionSubscriptionId = null;
    public ?int $actionPlanId = null;
    public string $actionTitle = '';
    public string $actionMessage = '';
    public $currentSubscription = null;

    public function mount(): void
    {
        $this->refreshCurrentSubscription();
    }

    public function setTab(string $tab): void
    {
        abort_unless(in_array($tab, ['subscriptions', 'plans'], true), 422);
        $this->activeTab = $tab;
        $this->resetValidation();
    }

    public function updatedSearch(): void { $this->resetSubscriptionsPage(); }
    public function updatedStatusFilter(): void { $this->resetSubscriptionsPage(); }
    public function updatedPeriodFilter(): void { $this->resetSubscriptionsPage(); }
    public function updatedPerPage(): void { $this->resetSubscriptionsPage(); }
    public function updatedPlanSearch(): void { $this->resetPage('plansPage'); }
    public function updatedPlanPeriodFilter(): void { $this->resetPage('plansPage'); }
    public function updatedPlanPerPage(): void { $this->resetPage('plansPage'); }

    private function resetSubscriptionsPage(): void
    {
        $this->resetPage('subscriptionsPage');
        $this->selectedSubscriptions = [];
        $this->selectPage = false;
    }

    public function updatedSelectPage(bool $value): void
    {
        if (! $value) {
            $this->selectedSubscriptions = [];
            return;
        }

        $this->selectedSubscriptions = $this->subscriptionsQuery()
            ->limit($this->perPage)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function clearSelection(): void
    {
        $this->selectedSubscriptions = [];
        $this->selectPage = false;
    }

    public function confirmCancel(int $subscriptionId): void
    {
        $subscription = $this->ownedSubscription($subscriptionId);
        $this->actionType = 'cancel';
        $this->actionSubscriptionId = $subscription->id;
        $this->actionPlanId = null;
        $this->actionTitle = 'Cancel subscription?';
        $this->actionMessage = 'Cancel '.($subscription->plan?->name ?? 'this subscription').'? Your projects and BOQ data will be preserved.';
        $this->showActionModal = true;
    }

    public function confirmSubscribe(int $planId): void
    {
        $plan = Plan::query()->whereKey($planId)->where('is_active', true)->where('is_archived', false)->firstOrFail();
        $this->actionType = 'subscribe';
        $this->actionPlanId = $plan->id;
        $this->actionSubscriptionId = null;
        $this->actionTitle = 'Choose '.$plan->name.'?';
        $this->actionMessage = 'A pending subscription will be created. Access is activated only after payment is confirmed.';
        $this->showActionModal = true;
    }

    public function confirmBulkCancel(): void
    {
        if (empty($this->selectedSubscriptions)) {
            return;
        }

        $this->actionType = 'bulk_cancel';
        $this->actionPlanId = null;
        $this->actionSubscriptionId = null;
        $this->actionTitle = 'Cancel selected subscriptions?';
        $this->actionMessage = count($this->selectedSubscriptions).' selected subscription(s) will be cancelled. Project and BOQ data will remain.';
        $this->showActionModal = true;
    }

    public function closeActionModal(): void
    {
        $this->showActionModal = false;
        $this->actionType = '';
        $this->actionSubscriptionId = null;
        $this->actionPlanId = null;
        $this->actionTitle = '';
        $this->actionMessage = '';
    }

    public function performAction(): void
    {
        match ($this->actionType) {
            'cancel' => $this->cancelOne(),
            'subscribe' => $this->subscribeToPlan(),
            'bulk_cancel' => $this->bulkCancel(),
            default => null,
        };

        $this->closeActionModal();
        $this->refreshCurrentSubscription();
    }

    private function cancelOne(): void
    {
        if (! $this->actionSubscriptionId) return;
        $subscription = $this->ownedSubscription($this->actionSubscriptionId);
        if (in_array($subscription->status, ['cancelled', 'expired'], true)) return;

        $subscription->update(['status' => 'cancelled', 'cancellation_date' => now(), 'auto_renewal' => false]);
        $subscription->entitlements()->update(['status' => 'revoked']);
        session()->flash('message', 'Subscription cancelled. Your project and BOQ data were preserved.');
    }

    private function bulkCancel(): void
    {
        if (empty($this->selectedSubscriptions)) return;
        $user = auth()->user();

        $subscriptions = Subscription::query()
            ->whereIn('id', array_map('intval', $this->selectedSubscriptions))
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhere('organisation_id', $user->organisation_id);
            })
            ->whereNotIn('status', ['cancelled', 'expired'])
            ->get();

        foreach ($subscriptions as $subscription) {
            $subscription->update(['status' => 'cancelled', 'cancellation_date' => now(), 'auto_renewal' => false]);
            $subscription->entitlements()->update(['status' => 'revoked']);
        }

        $count = $subscriptions->count();
        $this->clearSelection();
        session()->flash('message', $count.' subscription(s) cancelled successfully.');
    }

    private function subscribeToPlan(): void
    {
        if (! $this->actionPlanId) return;

        $plan = Plan::query()->whereKey($this->actionPlanId)->where('is_active', true)->where('is_archived', false)->firstOrFail();
        $user = auth()->user();

        $existingPending = Subscription::query()
            ->where('plan_id', $plan->id)
            ->where('status', 'pending')
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhere('organisation_id', $user->organisation_id);
            })
            ->latest()->first();

        if ($existingPending) {
            session()->flash('message', 'You already have a pending '.$plan->name.' subscription.');
            $this->activeTab = 'subscriptions';
            return;
        }

        $subscription = new Subscription(['plan_id' => $plan->id, 'auto_renewal' => $plan->auto_renewal]);
        $subscription->user_id = $user->id;
        $subscription->organisation_id = $user->organisation_id;
        $subscription->status = 'pending';
        $subscription->access_type = $plan->type;
        $subscription->payment_status = 'pending';
        $subscription->save();

        $this->activeTab = 'subscriptions';
        session()->flash('message', $plan->name.' selected. Complete payment to activate the subscription.');
    }

    private function ownedSubscription(int $subscriptionId): Subscription
    {
        $user = auth()->user();

        return Subscription::query()
            ->whereKey($subscriptionId)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhere('organisation_id', $user->organisation_id);
            })
            ->with('plan')
            ->firstOrFail();
    }

    private function subscriptionsQuery()
    {
        $user = auth()->user();

        return Subscription::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhere('organisation_id', $user->organisation_id);
            })
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('status', 'like', $term)
                        ->orWhere('payment_status', 'like', $term)
                        ->orWhereHas('plan', fn ($planQuery) => $planQuery->where('name', 'like', $term));
                });
            })
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->periodFilter !== 'all', function ($q) {
                match ($this->periodFilter) {
                    'current' => $q->whereIn('status', ['trial', 'active', 'grace_period'])
                        ->where(fn ($date) => $date->whereNull('end_date')->orWhere('end_date', '>=', now())),
                    'ending_30' => $q->whereBetween('end_date', [now(), now()->copy()->addDays(30)]),
                    'expired' => $q->where(function ($date) {
                        $date->where('status', 'expired')
                            ->orWhere(fn ($x) => $x->whereNotNull('end_date')->where('end_date', '<', now()));
                    }),
                    'this_year' => $q->whereYear('created_at', now()->year),
                    default => null,
                };
            })
            ->with('plan')
            ->latest();
    }

    private function plansQuery()
    {
        return Plan::query()
            ->where('is_active', true)
            ->where('is_archived', false)
            ->when($this->planSearch !== '', function ($q) {
                $term = '%'.$this->planSearch.'%';
                $q->where(fn ($inner) => $inner->where('name', 'like', $term)->orWhere('description', 'like', $term)->orWhere('code', 'like', $term));
            })
            ->when($this->planPeriodFilter !== 'all', function ($q) {
                match ($this->planPeriodFilter) {
                    'monthly' => $q->where('type', 'monthly'),
                    'quarterly' => $q->where('type', 'three_month'),
                    'six_month' => $q->where('type', 'six_month'),
                    'annual' => $q->where('type', 'annual'),
                    'lifetime' => $q->where('type', 'lifetime'),
                    default => null,
                };
            })
            ->orderBy('display_order')
            ->orderBy('price');
    }

    private function refreshCurrentSubscription(): void
    {
        $user = auth()->user();
        $this->currentSubscription = app(SubscriptionService::class)->currentSubscription($user, $user->organisation_id);
    }

    public function render()
    {
        return view('livewire.subscriptions.index', [
            'subscriptions' => $this->subscriptionsQuery()->paginate($this->perPage, ['*'], 'subscriptionsPage'),
            'plans' => $this->plansQuery()->paginate($this->planPerPage, ['*'], 'plansPage'),
            'subscription' => $this->currentSubscription,
        ]);
    }
}
