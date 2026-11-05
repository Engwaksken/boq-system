<?php

namespace App\Livewire\Boqs;

use App\Models\Boq;
use App\Models\BoqItemPriceHistory;
use App\Services\BoqPriceCheck;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Compares each matched BOQ item's rate with today's market price, and shows
 * the item's own rate change history (original -> suggested -> reviewed -> approved).
 * The comparison itself lives in BoqPriceCheck, shared with the API.
 */
class PriceCheck extends Component
{
    #[Locked]
    public int $boqId;

    /** all | above (our rate above market) | below | changed (market moved since matching) */
    public string $filter = 'all';

    /** Item whose rate history is open, if any. */
    public ?int $historyItemId = null;

    public function mount(Boq $boq): void
    {
        abort_unless(auth()->user()?->can('view', $boq), 403);
        $this->boqId = $boq->id;
    }

    public function updatedFilter(): void
    {
        if (! in_array($this->filter, BoqPriceCheck::FILTERS, true)) {
            $this->filter = 'all';
        }
    }

    public function showHistory(int $itemId): void
    {
        // Only items of this BOQ; the BOQ itself was authorised in mount and is re-checked in boq().
        $this->historyItemId = $this->boq()->items()->whereKey($itemId)->exists() ? $itemId : null;
    }

    public function closeHistory(): void
    {
        $this->historyItemId = null;
    }

    /** Re-render after rates change elsewhere on the BOQ page. */
    #[On('boq-prices-updated')]
    public function refreshPrices(): void {}

    public function placeholder(): string
    {
        return '<div class="boq-panel p-6 text-sm text-slate-500"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i> '.e(__('Loading...')).'</div>';
    }

    private function boq(): Boq
    {
        $boq = Boq::findOrFail($this->boqId);
        abort_unless(auth()->user()?->can('view', $boq), 403);

        return $boq;
    }

    public function render(BoqPriceCheck $priceCheck)
    {
        $boq = $this->boq();
        $all = $priceCheck->rows($boq, auth()->user()->organisation_id);
        $historyItem = $this->historyItemId ? $boq->items()->with('hardwarePrice')->find($this->historyItemId) : null;

        return view('livewire.boqs.price-check', [
            'boq' => $boq,
            'rows' => $priceCheck->filter($all, $this->filter),
            'summary' => $priceCheck->summary($boq, $all),
            'historyItem' => $historyItem,
            'history' => $historyItem
                ? BoqItemPriceHistory::query()->where('boq_item_id', $historyItem->id)->with('user')->latest('changed_at')->latest('id')->limit(100)->get()
                : collect(),
            'fieldLabels' => [
                'original_rate' => __('Original'),
                'ai_suggested_rate' => __('Suggested'),
                'reviewed_rate' => __('Reviewed'),
                'approved_rate' => __('Approved'),
            ],
        ]);
    }
}
