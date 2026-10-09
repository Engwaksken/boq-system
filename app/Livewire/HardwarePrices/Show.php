<?php

namespace App\Livewire\HardwarePrices;

use App\Models\HardwarePrice;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    use \App\Livewire\Concerns\ExportsTables;

    protected function exportViewData(): array
    {
        $price = $this->hardwarePrice->fresh();
        abort_unless($price && $price->isVisibleTo(auth()->user()->organisation_id), 403);

        return ['prices' => [$price], 'history' => $price->priceHistories()->latest('recorded_at')->get()];
    }
    public HardwarePrice $hardwarePrice;

    public function mount(HardwarePrice $hardwarePrice): void
    {
        abort_unless(
            $hardwarePrice->isVisibleTo(auth()->user()->organisation_id),
            403
        );

        $this->hardwarePrice = $hardwarePrice->load([
            'priceHistories' => fn ($q) => $q->orderBy('recorded_at', 'desc')->limit(50),
        ]);
    }

    public function render()
    {
        return view('livewire.hardware-prices.show');
    }
}
