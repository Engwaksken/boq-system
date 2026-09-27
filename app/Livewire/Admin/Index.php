<?php

namespace App\Livewire\Admin;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Transaction;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    /**
     * Livewire update requests skip route middleware, so re-check on every request.
     */
    public function boot(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
    }

    public string $search = '';
    public string $activeTab = 'subscriptions';
    public int $perPage = 20;

    public array $tabs = [
        'subscriptions' => 'Subscriptions',
        'plans' => 'Plans',
        'users' => 'Users',
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function updatedActiveTab(): void
    {
        if (! array_key_exists($this->activeTab, $this->tabs)) {
            $this->activeTab = 'subscriptions';
        }

        $this->search = '';
        $this->resetPage();
    }

    public function render()
    {
        $search = trim($this->search);
        $empty = new \Illuminate\Pagination\LengthAwarePaginator([], 0, $this->perPage);

        // Only the visible tab is queried.
        $subscriptions = $this->activeTab === 'subscriptions'
            ? Subscription::query()
                ->when($search !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))))
                ->with(['user', 'plan'])
                ->latest()
                ->paginate($this->perPage)
            : $empty;

        $plans = $this->activeTab === 'plans'
            ? Plan::query()
                ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                ->latest()
                ->paginate($this->perPage)
            : $empty;

        $users = $this->activeTab === 'users'
            ? User::query()
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
                ->with(['organisation', 'roles'])
                ->latest()
                ->paginate($this->perPage)
            : $empty;

        $stats = [
            'total_users' => User::count(),
            // Same definition as the subscriptions pages: current access, not just status = active.
            'active_subscriptions' => Subscription::whereIn('status', ['trial', 'active', 'grace_period'])
                ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', now()))
                ->count(),
            'total_revenue' => Transaction::where('status', 'successful')->sum('amount'),
            'plans_count' => Plan::where('is_active', true)->count(),
        ];

        return view('livewire.admin.index', [
            'subscriptions' => $subscriptions,
            'plans' => $plans,
            'users' => $users,
            'stats' => $stats,
        ]);
    }
}