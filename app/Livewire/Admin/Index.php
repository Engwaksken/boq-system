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

    public string $search = '';
    public string $activeTab = 'subscriptions';
    public int $perPage = 20;

    public array $tabs = [
        'subscriptions' => 'Subscriptions',
        'plans' => 'Plans',
        'users' => 'Users',
        'statistics' => 'Statistics',
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $subscriptions = Subscription::query()
            ->when($this->search, fn ($q) => $q->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('email', 'like', "%{$this->search}%")))
            ->with(['user', 'plan'])
            ->latest()
            ->paginate($this->perPage);

        $plans = Plan::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate($this->perPage);

        $users = User::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('email', 'like', "%{$this->search}%"))
            ->with(['organisation', 'roles'])
            ->latest()
            ->paginate($this->perPage);

        $stats = [
            'total_users' => User::count(),
            'active_subscriptions' => Subscription::where('status', 'active')->count(),
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