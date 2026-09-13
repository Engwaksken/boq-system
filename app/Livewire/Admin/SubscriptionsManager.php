<?php

namespace App\Livewire\Admin;

use App\Models\Plan;
use App\Models\Subscription;
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
        if ($this->sortBy === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDir = 'asc';
        }
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
            'revenue' => Subscription::where('status', 'active')->sum('amount'),
        ];

        return view('livewire.admin.subscriptions-manager', [
            'subscriptions' => $this->subscriptions,
            'stats' => $stats,
        ]);
    }
}