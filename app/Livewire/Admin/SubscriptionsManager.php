<?php

namespace App\Livewire\Admin;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
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

    private const SORTABLE = [
        'user.name',
        'plan.name',
        'status',
        'amount',
        'start_date',
        'end_date',
        'created_at',
    ];

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
            'active' => Subscription::query()->where('status', 'active')->count(),
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
