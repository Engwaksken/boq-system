<?php

namespace App\Livewire\Boqs;

use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\BoqItemPriceHistory;
use App\Models\PriceHistory;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Compares each matched BOQ item's rate with today's market price, and shows
 * the item's own rate change history (original -> suggested -> reviewed -> approved).
 */
class PriceCheck extends Component
{
    #[Locked]
    public int $boqId;

    /** all | above (our rate above market) | below | changed (market moved since matching) */
    public string $filter = 'all';

    /** Item whose rate history is open, if any. */
    public ?int $historyItemId = null;

    public const FILTERS = ['all', 'above', 'below', 'changed'];

    /** Rate fields from most to least final; the first set one is "our rate". */
    private const RATE_ORDER = [
        'approved_rate' => 'Approved',
        'reviewed_rate' => 'Reviewed',
        'ai_suggested_rate' => 'Suggested',
        'original_rate' => 'Original',
    ];

    public function mount(Boq $boq): void
    {
        abort_unless(auth()->user()?->can('view', $boq), 403);
        $this->boqId = $boq->id;
    }

    public function updatedFilter(): void
    {
        if (! in_array($this->filter, self::FILTERS, true)) {
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

    /**
     * One row per matched item whose market price the user may see.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(Boq $boq): Collection
    {
        $organisationId = auth()->user()->organisation_id;

        $items = $boq->items()
            ->whereNotNull('hardware_price_id')
            ->with('hardwarePrice')
            ->orderBy('id')
            ->get()
            ->filter(fn (BoqItem $item) => $item->hardwarePrice && $item->hardwarePrice->isVisibleTo($organisationId));

        // Market prices recorded up to the latest matching time, in one query.
        $latestMatch = $items->max('matched_at');
        $recorded = $latestMatch === null ? collect() : PriceHistory::query()
            ->whereIn('hardware_price_id', $items->pluck('hardware_price_id')->unique())
            ->where('recorded_at', '<=', $latestMatch)
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get(['hardware_price_id', 'price', 'recorded_at'])
            ->groupBy('hardware_price_id');

        return $items
            ->map(function (BoqItem $item) use ($recorded) {
                $market = $item->hardwarePrice;
                [$rateField, $rate] = $this->ourRate($item);
                $comparable = $rate !== null && strtoupper((string) $item->currency) === strtoupper((string) $market->currency);
                $difference = $comparable ? (float) $market->price - $rate : null;
                $quantity = (float) $item->quantity;

                // Market price when the item was matched: the latest recording at or before matching.
                $atMatch = $item->matched_at
                    ? ($recorded->get($item->hardware_price_id) ?? collect())
                        ->filter(fn (PriceHistory $h) => $h->recorded_at->lessThanOrEqualTo($item->matched_at))
                        ->last()?->price
                    : null;
                $sinceMatch = $atMatch !== null ? (float) $market->price - (float) $atMatch : null;

                return [
                    'item' => $item,
                    'market' => $market,
                    'rate_label' => $rateField ? self::RATE_ORDER[$rateField] : null,
                    'rate' => $rate,
                    'comparable' => $comparable,
                    'difference' => $difference,
                    'percent' => $comparable && $rate != 0.0 ? $difference / $rate * 100 : null,
                    'amount_ours' => $comparable ? $quantity * $rate : null,
                    'amount_market' => $comparable ? $quantity * (float) $market->price : null,
                    'since_match' => $sinceMatch,
                ];
            })
            ->values();
    }

    /** @return array{0: ?string, 1: ?float} */
    private function ourRate(BoqItem $item): array
    {
        foreach (array_keys(self::RATE_ORDER) as $field) {
            if ($item->{$field} !== null) {
                return [$field, (float) $item->{$field}];
            }
        }

        return [null, null];
    }

    public function render()
    {
        $boq = $this->boq();
        $all = $this->rows($boq);

        $rows = match ($this->filter) {
            'above' => $all->filter(fn ($r) => $r['difference'] !== null && $r['difference'] < 0),
            'below' => $all->filter(fn ($r) => $r['difference'] !== null && $r['difference'] > 0),
            'changed' => $all->filter(fn ($r) => $r['since_match'] !== null && abs($r['since_match']) >= 0.01),
            default => $all,
        };
        // Biggest gaps first, so what needs attention is on top.
        $rows = $rows->sortByDesc(fn ($r) => abs($r['percent'] ?? 0))->values();

        $comparable = $all->where('comparable', true);
        $totalOurs = $comparable->sum('amount_ours');
        $totalMarket = $comparable->sum('amount_market');

        $historyItem = $this->historyItemId ? $boq->items()->with('hardwarePrice')->find($this->historyItemId) : null;

        return view('livewire.boqs.price-check', [
            'boq' => $boq,
            'rows' => $rows,
            'summary' => [
                'matched' => $all->count(),
                'unmatched' => $boq->items()->whereNull('hardware_price_id')->count(),
                'above' => $all->filter(fn ($r) => $r['difference'] !== null && $r['difference'] < 0)->count(),
                'below' => $all->filter(fn ($r) => $r['difference'] !== null && $r['difference'] > 0)->count(),
                'changed' => $all->filter(fn ($r) => $r['since_match'] !== null && abs($r['since_match']) >= 0.01)->count(),
                'total_ours' => $totalOurs,
                'total_market' => $totalMarket,
                'total_difference' => $totalMarket - $totalOurs,
                'total_percent' => $totalOurs != 0.0 ? ($totalMarket - $totalOurs) / $totalOurs * 100 : null,
            ],
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
