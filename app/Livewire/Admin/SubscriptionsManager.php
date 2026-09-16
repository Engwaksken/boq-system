<?php

namespace App\Livewire\Admin;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Transaction;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class SubscriptionsManager extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public int $perPage = 20;
    public string $sortBy = 'created_at';
    public string $sortDir = 'desc';

    public array $perPageOptions = [10, 20, 50, 100];

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
        abort_unless(in_array($field, ['created_at', 'status', 'start_date', 'end_date', 'payment_status'], true), 422);
        if ($this->sortBy === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDir = 'asc';
        }
    }


    public function suspend(int $id): void
    {
        $subscription = Subscription::findOrFail($id);
        $subscription->update(['status' => 'suspended']);
        $subscription->entitlements()->update(['status' => 'suspended']);
        session()->flash('message', 'Subscription suspended.');
    }

    public function reactivate(int $id): void
    {
        $subscription = Subscription::findOrFail($id);
        $subscription->update(['status' => 'active']);
        $subscription->entitlements()->update(['status' => 'active']);
        session()->flash('message', 'Subscription reactivated.');
    }

    public function cancelSubscription(int $id): void
    {
        $subscription = Subscription::findOrFail($id);
        $subscription->update(['status' => 'cancelled', 'cancellation_date' => now(), 'auto_renewal' => false]);
        $subscription->entitlements()->update(['status' => 'revoked']);
        session()->flash('message', 'Subscription cancelled. User data was preserved.');
    }

    public function extend(int $id, int $days = 30): void
    {
        abort_unless(in_array($days, [7, 30, 90, 365], true), 422);
        $subscription = Subscription::findOrFail($id);
        $base = $subscription->end_date && $subscription->end_date->isFuture() ? $subscription->end_date->copy() : now();
        $subscription->end_date = $base->addDays($days);
        $subscription->renewal_date = $subscription->end_date->copy();
        $subscription->save();
        $subscription->entitlements()->update(['expires_at' => $subscription->end_date]);
        session()->flash('message', "Subscription extended by {$days} days.");
    }

    public function render()
    {
        $subscriptions = Subscription::query()
            ->when($this->search, fn ($q) => $q->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('email', 'like', "%{$this->search}%")))
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->with(['user', 'plan'])
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate($this->perPage);

        $stats = [
            'total' => Subscription::count(),
            'active' => Subscription::where('status', 'active')->count(),
            'expired' => Subscription::where('status', 'expired')->count(),
            'cancelled' => Subscription::where('status', 'cancelled')->count(),
            'revenue' => Transaction::where('status', 'successful')->sum('amount'),
        ];

        return view('livewire.admin.subscriptions-manager', [
            'subscriptions' => $subscriptions,
            'stats' => $stats,
        ]);
    }
}