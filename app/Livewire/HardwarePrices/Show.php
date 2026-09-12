<?php

namespace App\Livewire\HardwarePrices;

use App\Models\HardwarePrice;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public HardwarePrice $hardwarePrice;

    public function mount(HardwarePrice $hardwarePrice): void
    {
        abort_unless(
            $hardwarePrice->organisation_id === auth()->user()->organisation_id,
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