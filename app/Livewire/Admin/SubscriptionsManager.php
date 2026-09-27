<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithBulkSelection;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class SubscriptionsManager extends Component
{
    use \App\Livewire\Concerns\UsesPreferredPerPage;
    use WithBulkSelection;
    use WithPagination;

    private const ACTIVATABLE = ['pending', 'past_due', 'failed', 'expired'];

    private const DELETABLE = ['pending', 'failed', 'cancelled', 'expired'];

    public ?int $extendingId = null;

    public int $extendDays = 30;

    public string $search = '';
    public string $statusFilter = 'all';
    public int $perPage = 20;
    public string $sortBy = 'created_at';
    public string $sortDir = 'desc';

    public array $perPageOptions = [10, 20, 50, 100];

    private const SORTABLE = [
        'user.name',
        'plan.name',
        'status',
        'amount',
        'start_date',
        'end_date',
        'created_at',
    ];

    /**
     * Livewire update requests skip route middleware, so re-check on every request.
     */
    public function boot(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
    }

    /**
     * Manually activate a subscription (e.g. a confirmed bank transfer).
     */
    public function activate(int $id, SubscriptionService $service): void
    {
        $subscription = Subscription::with('plan')->findOrFail($id);

        if (! in_array($subscription->status, self::ACTIVATABLE, true)) {
            session()->flash('message', 'Only pending, overdue, failed or expired subscriptions can be activated.');

            return;
        }

        $this->activateSubscription($subscription, $service);

        session()->flash('message', 'Subscription activated.');
    }

    public function cancel(int $id): void
    {
        $subscription = Subscription::findOrFail($id);

        if (in_array($subscription->status, ['cancelled', 'expired'], true)) {
            return;
        }

        $this->cancelSubscription($subscription);

        session()->flash('message', 'Subscription cancelled and its entitlements revoked.');
    }

    public function openExtend(int $id): void
    {
        $this->extendingId = Subscription::findOrFail($id)->id;
        $this->extendDays = 30;
        $this->resetValidation();
    }

    public function closeExtend(): void
    {
        $this->extendingId = null;
    }

    public function applyExtension(): void
    {
        $this->validate(['extendDays' => ['required', 'integer', 'min:1', 'max:3650']]);

        $subscription = Subscription::with('plan')->findOrFail($this->extendingId);

        if ($subscription->end_date === null) {
            session()->flash('message', 'This subscription has no end date (lifetime) and cannot be extended.');
            $this->closeExtend();

            return;
        }

        DB::transaction(function () use ($subscription) {
            // Extend from today when it already lapsed, otherwise from the current end date.
            $base = $subscription->end_date->isPast() ? now() : $subscription->end_date;
            $endDate = $base->copy()->addDays($this->extendDays);
            $graceDays = (int) ($subscription->plan?->grace_period_days ?? 0);

            $subscription->update([
                'end_date' => $endDate,
                'renewal_date' => $endDate,
                'grace_period_end_date' => $graceDays > 0 ? $endDate->copy()->addDays($graceDays) : null,
                'status' => $subscription->status === 'expired' ? 'active' : $subscription->status,
            ]);

            $subscription->refresh();

            // Entitlements carry their own expiry; keep them in step with the new period.
            $changes = ['expires_at' => $subscription->grace_period_end_date ?? $subscription->end_date];

            if ($subscription->status === 'active') {
                $changes['status'] = 'active';
            }

            $subscription->entitlements()
                ->where('is_permanent', false)
                ->whereIn('status', ['active', 'expired'])
                ->update($changes);
        });

        session()->flash('message', "Subscription extended by {$this->extendDays} day(s).");
        $this->closeExtend();
    }

    public function bulkActivate(SubscriptionService $service): void
    {
        $subscriptions = Subscription::with('plan')
            ->whereKey($this->selectedIds())
            ->whereIn('status', self::ACTIVATABLE)
            ->get();

        $subscriptions->each(fn ($subscription) => $this->activateSubscription($subscription, $service));

        $this->finishBulkAction($subscriptions->count(), 'activated');
    }

    public function bulkCancel(): void
    {
        $subscriptions = Subscription::whereKey($this->selectedIds())
            ->whereNotIn('status', ['cancelled', 'expired'])
            ->get();

        $subscriptions->each(fn ($subscription) => $this->cancelSubscription($subscription));

        $this->finishBulkAction($subscriptions->count(), 'cancelled');
    }

    public function bulkDelete(): void
    {
        $count = Subscription::whereKey($this->selectedIds())
            ->whereIn('status', self::DELETABLE)
            ->get()
            ->each->delete()
            ->count();

        $this->clearSelection();

        session()->flash('message', "{$count} subscription(s) deleted. Active subscriptions are never deleted; cancel them first.");
    }

    private function activateSubscription(Subscription $subscription, SubscriptionService $service): void
    {
        if ($subscription->status === 'expired') {
            // Start a fresh period instead of re-activating the lapsed one.
            $subscription->start_date = now();
        }

        $service->activate($subscription);
    }

    private function cancelSubscription(Subscription $subscription): void
    {
        DB::transaction(function () use ($subscription) {
            $subscription->update([
                'status' => 'cancelled',
                'cancellation_date' => now(),
                'auto_renewal' => false,
            ]);

            $subscription->entitlements()->update(['status' => 'revoked']);
        });
    }

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
        if (! in_array($this->perPage, $this->perPageOptions, true)) {
            $this->perPage = 20;
        }

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
        $query = Subscription::query()
            ->when($this->search !== '', function ($query): void {
                $search = '%' . trim($this->search) . '%';

                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->whereHas('user', fn ($user) => $user
                            ->where('name', 'like', $search)
                            ->orWhere('email', 'like', $search))
                        ->orWhereHas('plan', fn ($plan) => $plan->where('name', 'like', $search));
                });
            })
            ->when(
                $this->statusFilter !== 'all',
                fn ($query) => $query->where('status', $this->statusFilter)
            )
            ->with(['user', 'plan']);

        match ($this->sortBy) {
            'user.name' => $query->orderBy(
                User::select('name')->whereColumn('users.id', 'subscriptions.user_id'),
                $this->sortDir
            ),
            'plan.name' => $query->orderBy(
                Plan::select('name')->whereColumn('plans.id', 'subscriptions.plan_id'),
                $this->sortDir
            ),
            'amount' => $query->orderBy(
                Plan::select('price')->whereColumn('plans.id', 'subscriptions.plan_id'),
                $this->sortDir
            ),
            default => $query->orderBy($this->sortBy, $this->sortDir),
        };

        $subscriptions = $query->paginate($this->perPage);

        $stats = [
            'total' => Subscription::query()->count(),
            'active' => Subscription::query()
                ->whereIn('status', ['trial', 'active', 'grace_period'])
                ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', now()))
                ->count(),
            'expired' => Subscription::query()->where('status', 'expired')->count(),
            'cancelled' => Subscription::query()->where('status', 'cancelled')->count(),
            'revenue' => Transaction::query()
                ->where('status', 'successful')
                ->whereBetween('completed_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount'),
        ];

        return view('livewire.admin.subscriptions-manager', [
            'subscriptions' => $subscriptions,
            'stats' => $stats,
        ]);
    }
}
