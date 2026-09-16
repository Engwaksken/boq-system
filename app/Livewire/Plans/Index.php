<?php

namespace App\Livewire\Plans;

use App\Models\Plan;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public int $perPage = 10;
    public string $sortBy = 'display_order';
    public string $sortDir = 'asc';

    public array $perPageOptions = [10, 20, 50];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        $allowedFields = [
            'display_order',
            'name',
            'price',
            'type',
            'duration_days',
        ];

        if (! in_array($field, $allowedFields, true)) {
            return;
        }

        if ($this->sortBy === $field) {
            $this->sortDir = $this->sortDir === 'asc'
                ? 'desc'
                : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDir = 'asc';
        }

        $this->resetPage();
    }

    public function render()
    {
        $search = trim($this->search);

        $plans = Plan::query()
            ->where('is_active', true)
            ->where('is_archived', false)
            ->when(
                $search !== '',
                function ($query) use ($search) {
                    $query->where(function ($subQuery) use ($search) {
                        $subQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhere('currency', 'like', "%{$search}%")
                            ->orWhere('type', 'like', "%{$search}%");
                    });
                }
            )
            ->with('features')
            ->orderBy($this->sortBy, $this->sortDir)
            ->orderBy('id')
            ->paginate($this->perPage);

        $stats = [
            'available_plans' => Plan::query()
                ->where('is_active', true)
                ->where('is_archived', false)
                ->count(),

            'monthly_plans' => Plan::query()
                ->where('is_active', true)
                ->where('is_archived', false)
                ->where('type', 'monthly')
                ->count(),

            'annual_plans' => Plan::query()
                ->where('is_active', true)
                ->where('is_archived', false)
                ->where('type', 'annual')
                ->count(),
        ];

        return view('livewire.plans.index', [
            'plans' => $plans,
            'stats' => $stats,
        ]);
    }
}