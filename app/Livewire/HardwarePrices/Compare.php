<?php

namespace App\Livewire\HardwarePrices;

use App\Http\Controllers\Api\HardwarePriceController;
use App\Models\HardwarePrice;
use Illuminate\Http\Request;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Compare extends Component
{
    public array $selectedIds = [];

    public $comparison = null;

    public $summary = null;

    public $prices;

    public function mount(): void
    {
        $this->prices = HardwarePrice::query()
            ->active()
            ->where('organisation_id', auth()->user()->organisation_id)
            ->orderBy('item_name')
            ->get(['id', 'item_name', 'brand', 'category', 'price', 'currency', 'supplier', 'location']);
    }

    public function compare(): void
    {
        $ids = array_values(array_filter($this->selectedIds));

        if (count($ids) < 2 || count($ids) > 10) {
            session()->flash('error', 'Please select 2 to 10 items to compare.');

            return;
        }

        $request = Request::create('/api/v1/hardware-prices/compare', 'POST', ['ids' => $ids]);
        $request->setUserResolver(fn () => auth()->user());

        $response = app(HardwarePriceController::class)->compare($request);
        $payload = json_decode($response->getContent(), true);

        if (($payload['success'] ?? false) === false) {
            session()->flash('error', $payload['message'] ?? 'Comparison failed.');

            return;
        }

        $this->comparison = $payload['data']['items'];
        $this->summary = $payload['data']['summary'];
    }

    public function render()
    {
        return view('livewire.hardware-prices.compare');
    }
}