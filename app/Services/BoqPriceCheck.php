<?php

namespace App\Services;

use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\PriceHistory;
use Illuminate\Support\Collection;

/**
 * Compares each matched BOQ item's rate with today's market price. Shared by the
 * BOQ Price check tab and the API so both show the same figures.
 */
class BoqPriceCheck
{
    public const FILTERS = ['all', 'above', 'below', 'changed'];

    /** Rate fields from most to least final; the first one set is "our rate". */
    public const RATE_ORDER = [
        'approved_rate' => 'Approved',
        'reviewed_rate' => 'Reviewed',
        'ai_suggested_rate' => 'Suggested',
        'original_rate' => 'Original',
    ];

    /**
     * One row per matched item whose market price the organisation may see.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(Boq $boq, ?int $organisationId): Collection
    {
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

                return [
                    'item' => $item,
                    'market' => $market,
                    'rate_field' => $rateField,
                    'rate_label' => $rateField ? self::RATE_ORDER[$rateField] : null,
                    'rate' => $rate,
                    'comparable' => $comparable,
                    'difference' => $difference,
                    'percent' => $comparable && $rate != 0.0 ? $difference / $rate * 100 : null,
                    'amount_ours' => $comparable ? $quantity * $rate : null,
                    'amount_market' => $comparable ? $quantity * (float) $market->price : null,
                    'since_match' => $atMatch !== null ? (float) $market->price - (float) $atMatch : null,
                ];
            })
            ->values();
    }

    /** Rows for one filter, biggest gaps first so what needs attention is on top. */
    public function filter(Collection $rows, string $filter): Collection
    {
        return $rows
            ->filter(fn (array $row) => match ($filter) {
                'above' => $this->isAbove($row),
                'below' => $this->isBelow($row),
                'changed' => $this->hasChanged($row),
                default => true,
            })
            ->sortByDesc(fn (array $row) => abs($row['percent'] ?? 0))
            ->values();
    }

    /** @return array<string, int|float|null> */
    public function summary(Boq $boq, Collection $rows): array
    {
        $comparable = $rows->where('comparable', true);
        $totalOurs = (float) $comparable->sum('amount_ours');
        $totalMarket = (float) $comparable->sum('amount_market');

        return [
            'matched' => $rows->count(),
            'unmatched' => $boq->items()->whereNull('hardware_price_id')->count(),
            'above' => $rows->filter(fn ($r) => $this->isAbove($r))->count(),
            'below' => $rows->filter(fn ($r) => $this->isBelow($r))->count(),
            'changed' => $rows->filter(fn ($r) => $this->hasChanged($r))->count(),
            'total_ours' => $totalOurs,
            'total_market' => $totalMarket,
            'total_difference' => $totalMarket - $totalOurs,
            'total_percent' => $totalOurs != 0.0 ? ($totalMarket - $totalOurs) / $totalOurs * 100 : null,
        ];
    }

    /** Our rate is above today's market. */
    private function isAbove(array $row): bool
    {
        return $row['difference'] !== null && $row['difference'] < 0;
    }

    /** Our rate is below today's market. */
    private function isBelow(array $row): bool
    {
        return $row['difference'] !== null && $row['difference'] > 0;
    }

    /** The market price moved since the item was matched. */
    private function hasChanged(array $row): bool
    {
        return $row['since_match'] !== null && abs($row['since_match']) >= 0.01;
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
}
