<?php

namespace App\Livewire\HardwarePrices;

use App\Models\HardwarePrice;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public ?string $category = null;

    public ?string $supplier = null;

    public ?string $location = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function updatedSupplier(): void
    {
        $this->resetPage();
    }

    public function updatedLocation(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $organisationId = auth()->user()->organisation_id;

        $prices = HardwarePrice::query()
            ->active()
            ->where('organisation_id', $organisationId)
            ->when($this->search !== '', fn ($q) => $q->search($this->search))
            ->when($this->category, fn ($q) => $q->byCategory($this->category))
            ->when($this->supplier, fn ($q) => $q->bySupplier($this->supplier))
            ->when($this->location, fn ($q) => $q->byLocation($this->location))
            ->orderBy('fetched_at', 'desc')
            ->paginate(20);

        $categories = HardwarePrice::query()
            ->where('organisation_id', $organisationId)
            ->where('is_active', true)
            ->distinct()
            ->pluck('category')
            ->filter()
            ->values();

        $suppliers = HardwarePrice::query()
            ->where('organisation_id', $organisationId)
            ->where('is_active', true)
            ->distinct()
            ->pluck('supplier')
            ->filter()
            ->values();

        $locations = HardwarePrice::query()
            ->where('organisation_id', $organisationId)
            ->where('is_active', true)
            ->distinct()
            ->pluck('location')
            ->filter()
            ->values();

        return view('livewire.hardware-prices.index', [
            'prices' => $prices,
            'categories' => $categories,
            'suppliers' => $suppliers,
            'locations' => $locations,
        ]);
    }
}