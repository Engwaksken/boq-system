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

    /** How many months of history to summarise (one of MONTH_OPTIONS). */
    public int $months = 12;

    /** "monthly" summary or the individual "records". */
    public string $historyView = 'monthly';

    public const MONTH_OPTIONS = [6, 12, 24];

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

    public function updatedMonths(): void
    {
        if (! in_array($this->months, self::MONTH_OPTIONS, true)) {
            $this->months = 12;
        }
    }

    public function updatedHistoryView(): void
    {
        if (! in_array($this->historyView, ['monthly', 'records'], true)) {
            $this->historyView = 'monthly';
        }
    }

    public function render()
    {
        return view('livewire.hardware-prices.show', [
            'monthly' => $this->hardwarePrice->monthlyHistory($this->months),
            'monthOptions' => self::MONTH_OPTIONS,
        ]);
    }
}
