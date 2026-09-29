<?php

namespace App\Services;

use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\BoqLocationPrice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One BOQ priced for several locations: the saved prices per location, a
 * side-by-side comparison, and switching the BOQ to one location's prices.
 */
class BoqLocationPricing
{
    /**
     * @return Collection<int, array{key: string, location: string, priced_items: int, total_items: int, total: float, priced_at: ?string, current: bool}>
     */
    public function locations(Boq $boq): Collection
    {
        $items = $boq->items()->get(['id', 'quantity', 'location']);
        $quantities = $items->pluck('quantity', 'id');
        $currentKey = BoqLocationPrice::keyFor((string) $items->pluck('location')->filter()->countBy()->sortDesc()->keys()->first());

        return BoqLocationPrice::query()
            ->where('boq_id', $boq->id)
            ->get()
            ->groupBy('location_key')
            ->map(fn (Collection $prices, string $key) => [
                'key' => $key,
                'location' => (string) $prices->sortByDesc('priced_at')->first()->location,
                'priced_items' => $prices->count(),
                'total_items' => $items->count(),
                'total' => round($prices->sum(fn ($p) => (float) $p->rate * (float) ($quantities[$p->boq_item_id] ?? 0)), 2),
                'priced_at' => optional($prices->max('priced_at'))->toDateTimeString(),
                'current' => $key === $currentKey,
            ])
            ->sortByDesc('priced_at')
            ->values();
    }

    /**
     * Rates of every item for the chosen locations, with each item's cheapest location.
     *
     * @param  list<string>  $keys
     * @return array{locations: list<array{key: string, location: string, total: float, priced_items: int}>, rows: list<array{item: BoqItem, rates: array<string, ?float>, lowest: ?string}>}
     */
    public function compare(Boq $boq, array $keys): array
    {
        $keys = array_values(array_unique(array_filter($keys)));
        $prices = BoqLocationPrice::query()
            ->where('boq_id', $boq->id)
            ->whereIn('location_key', $keys)
            ->get()
            ->groupBy('boq_item_id');
        $names = BoqLocationPrice::query()->where('boq_id', $boq->id)->whereIn('location_key', $keys)
            ->latest('priced_at')->get(['location_key', 'location'])->unique('location_key')->pluck('location', 'location_key');

        $totals = array_fill_keys($keys, 0.0);
        $counts = array_fill_keys($keys, 0);
        $rows = [];

        foreach ($boq->items()->orderBy('id')->get() as $item) {
            $rates = [];
            foreach ($keys as $key) {
                $price = $prices->get($item->id)?->firstWhere('location_key', $key);
                $rates[$key] = $price ? (float) $price->rate : null;
                if ($price) {
                    $totals[$key] += (float) $price->rate * (float) $item->quantity;
                    $counts[$key]++;
                }
            }
            $known = array_filter($rates, fn ($rate) => $rate !== null);
            $rows[] = [
                'item' => $item,
                'rates' => $rates,
                'lowest' => count($known) > 1 ? array_search(min($known), $known, true) : null,
            ];
        }

        return [
            'locations' => array_map(fn ($key) => [
                'key' => $key,
                'location' => (string) ($names[$key] ?? $key),
                'total' => round($totals[$key], 2),
                'priced_items' => $counts[$key],
            ], $keys),
            'rows' => $rows,
        ];
    }

    /**
     * Switches the BOQ's suggested prices to one location's saved prices.
     * Approved items keep their approved price.
     *
     * @return array{applied: int, missing: int}
     */
    public function apply(Boq $boq, string $key): array
    {
        $prices = BoqLocationPrice::query()->where('boq_id', $boq->id)->where('location_key', $key)->get()->keyBy('boq_item_id');
        $applied = 0;
        $missing = 0;

        DB::transaction(function () use ($boq, $prices, &$applied, &$missing): void {
            foreach ($boq->items()->lockForUpdate()->get() as $item) {
                if ($item->status === 'approved') {
                    continue;
                }
                $price = $prices->get($item->id);
                if (! $price) {
                    $missing++;
                    continue;
                }
                $item->fill([
                    'ai_suggested_rate' => $price->rate,
                    'location' => $price->location,
                    'pricing_source' => $price->source ?: 'location',
                    'hardware_price_id' => $price->hardware_price_id,
                    'pricing_status' => 'priced',
                    'pricing_date' => optional($price->priced_at)->toDateString() ?? now()->toDateString(),
                    'reviewed_rate' => null,
                    'status' => 'pending',
                ])->save();
                $applied++;
            }
        });

        return ['applied' => $applied, 'missing' => $missing];
    }
}
