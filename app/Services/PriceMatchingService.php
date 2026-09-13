<?php

namespace App\Services;

use App\Models\BoqItem;
use App\Models\HardwarePrice;
use Illuminate\Support\Collection;

class PriceMatchingService
{
    public function searchForManualMatch(
        BoqItem $boqItem,
        string $search = '',
        int $limit = 12,
        ?string $location = null
    ): Collection {
        $boqItem->loadMissing('boq.project');
        $organisationId = $this->organisationId($boqItem);
        $location = trim((string) ($location ?? $boqItem->boq->project->location));

        $query = HardwarePrice::query()
            ->active()
            ->where('organisation_id', $organisationId)
            ->whereRaw('LOWER(currency) = ?', [strtolower($boqItem->currency)]);

        if (trim($search) !== '') {
            $query->search(trim($search));
        }

        $prices = $query->orderByDesc('fetched_at')->limit(50)->get();

        return $prices
            ->sortByDesc(fn (HardwarePrice $price) => $this->locationsMatch($location, $price->location) ? 1 : 0)
            ->take(min(max($limit, 1), 20))
            ->values();
    }

    public function findMatches(BoqItem $boqItem, int $limit = 5, ?string $location = null): Collection
    {
        $boqItem->loadMissing('boq.project');
        $organisationId = $this->organisationId($boqItem);
        $location = trim((string) ($location ?? $boqItem->boq->project->location));

        $query = HardwarePrice::active()
            ->where('organisation_id', $organisationId)
            ->whereRaw('LOWER(currency) = ?', [strtolower($boqItem->currency)]);

        $matches = $query->get()->map(function ($price) use ($boqItem, $location) {
            $similarity = $this->calculateSimilarity($boqItem, $price);
            $locationMatches = $this->locationsMatch($location, $price->location);

            return [
                'hardware_price' => $price,
                'similarity_score' => $similarity,
                'location_matches' => $locationMatches,
                'match_reasons' => $this->getMatchReasons($boqItem, $price),
            ];
        })
            ->filter(fn ($match) => $match['similarity_score'] >= 0.5
                && ($location === '' || $match['location_matches'] || blank($match['hardware_price']->location)))
            ->sortByDesc(fn ($match) => ($match['location_matches'] ? 1 : 0) + $match['similarity_score'])
            ->take($limit)
            ->values();

        return $matches;
    }

    private function calculateSimilarity(BoqItem $boqItem, HardwarePrice $price): float
    {
        $score = 0.0;
        $maxScore = 0.0;

        $itemText = strtolower(trim($boqItem->description.' '.($boqItem->item_code ?? '')));
        $priceText = strtolower(trim($price->item_name.' '.($price->brand ?? '').' '.($price->specification ?? '')));

        $itemWords = array_filter(explode(' ', $itemText));
        $priceWords = array_filter(explode(' ', $priceText));

        $commonWords = array_intersect($itemWords, $priceWords);
        $totalWords = array_unique(array_merge($itemWords, $priceWords));

        if (count($totalWords) > 0) {
            $wordSimilarity = count($commonWords) / count($totalWords);
            $score += $wordSimilarity * 0.6;
            $maxScore += 0.6;
        }

        if (str_contains($itemText, strtolower($price->category))
            || ($boqItem->unit && str_contains($priceText, strtolower($boqItem->unit)))) {
            $score += 0.2;
        }
        $maxScore += 0.2;

        if ($boqItem->unit && $price->unit) {
            $unitMatch = $this->unitsMatch($boqItem->unit, $price->unit);
            if ($unitMatch) {
                $score += 0.2;
            }
        }
        $maxScore += 0.2;

        return $maxScore > 0 ? $score / $maxScore : 0;
    }

    private function unitsMatch(string $unit1, string $unit2): bool
    {
        $unit1 = strtolower($unit1);
        $unit2 = strtolower($unit2);

        $unitGroups = [
            ['bag', 'bags', 'sack', 'sacks'],
            ['ton', 'tons', 'tonne', 'tonnes', 't'],
            ['kg', 'kilogram', 'kilograms', 'kgs'],
            ['piece', 'pieces', 'pc', 'pcs', 'each', 'ea'],
            ['meter', 'meters', 'metre', 'metres', 'm', 'lm', 'rm'],
            ['sqm', 'm2', 'square meter', 'square metre'],
            ['litre', 'litres', 'liter', 'liters', 'l'],
            ['sheet', 'sheets', 'pc', 'pcs'],
            ['roll', 'rolls'],
            ['box', 'boxes'],
        ];

        foreach ($unitGroups as $group) {
            if (in_array($unit1, $group) && in_array($unit2, $group)) {
                return true;
            }
        }

        return $unit1 === $unit2;
    }

    private function locationsMatch(string $requested, ?string $available): bool
    {
        if ($requested === '' || blank($available)) {
            return false;
        }

        $requested = strtolower(trim($requested));
        $available = strtolower(trim($available));

        $requested = ' '.preg_replace('/[^a-z0-9]+/', ' ', $requested).' ';
        $available = ' '.preg_replace('/[^a-z0-9]+/', ' ', $available).' ';

        return $requested === $available
            || str_contains($requested, $available)
            || str_contains($available, $requested);
    }

    private function getMatchReasons(BoqItem $boqItem, HardwarePrice $price): array
    {
        $reasons = [];

        $itemWords = array_filter(explode(' ', strtolower($boqItem->description)));
        $priceWords = array_filter(explode(' ', strtolower($price->item_name.' '.($price->brand ?? '').' '.($price->specification ?? ''))));
        $common = array_intersect($itemWords, $priceWords);

        if (! empty($common)) {
            $reasons[] = 'Name match: '.implode(', ', array_slice($common, 0, 3));
        }

        if (str_contains(strtolower($boqItem->description), strtolower($price->category))) {
            $reasons[] = 'Category match: '.$price->category;
        }

        if ($boqItem->unit && $price->unit && $this->unitsMatch($boqItem->unit, $price->unit)) {
            $reasons[] = 'Unit match: '.$price->unit;
        }

        if ($boqItem->boq->project->location && $price->location) {
            if (stripos($price->location, $boqItem->boq->project->location) !== false ||
                stripos($boqItem->boq->project->location, $price->location) !== false) {
                $reasons[] = 'Location match: '.$price->location;
            }
        }

        return $reasons;
    }

    public function applyPriceToBoqItem(
        BoqItem $boqItem,
        HardwarePrice $price,
        ?float $confidence = null,
        ?string $location = null
    ): void {
        $boqItem->fill([
            'ai_suggested_rate' => $price->price,
            'hardware_price_id' => $price->id,
            'match_type' => 'automatic',
            'matched_by' => null,
            'matched_at' => now(),
            'reviewed_rate' => null,
            'approved_rate' => null,
            'location' => $price->location ?: $location,
            'pricing_source' => 'hardware_price:'.$price->id,
            'pricing_date' => $price->fetched_at?->toDateString() ?? now()->toDateString(),
            'ai_confidence' => $confidence === null ? null : round($confidence * 100, 2),
            'status' => 'pending',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);
        $boqItem->save();
    }

    public function applyManualPrice(BoqItem $boqItem, HardwarePrice $price, int $userId): void
    {
        $boqItem->loadMissing('boq.project');

        abort_unless(
            $price->is_active
            && $price->organisation_id === $this->organisationId($boqItem)
            && strtolower($price->currency) === strtolower($boqItem->currency),
            403
        );

        $boqItem->fill([
            'ai_suggested_rate' => $price->price,
            'hardware_price_id' => $price->id,
            'match_type' => 'manual',
            'matched_by' => $userId,
            'matched_at' => now(),
            'reviewed_rate' => null,
            'approved_rate' => null,
            'location' => $price->location ?: $boqItem->boq->project->location,
            'pricing_source' => 'hardware_price:'.$price->id,
            'pricing_date' => $price->fetched_at?->toDateString() ?? now()->toDateString(),
            'ai_confidence' => null,
            'status' => 'pending',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
        ])->save();
    }

    private function organisationId(BoqItem $boqItem): ?int
    {
        return $boqItem->boq->organisation_id ?: $boqItem->boq->project->organisation_id;
    }

    public function getPriceComparison(BoqItem $boqItem): array
    {
        $matches = $this->findMatches($boqItem, 10);

        $currentPrice = $boqItem->approved_rate ?? $boqItem->original_rate ?? 0;
        $currency = $boqItem->boq->currency ?? 'UGX';

        return [
            'boq_item' => [
                'description' => $boqItem->description,
                'quantity' => $boqItem->quantity,
                'unit' => $boqItem->unit,
                'current_rate' => $currentPrice,
                'currency' => $currency,
                'current_amount' => $boqItem->amount,
            ],
            'matches' => $matches->map(function ($match) use ($currentPrice) {
                $hp = $match['hardware_price'];
                $variance = $currentPrice > 0 ? round((($hp->price - $currentPrice) / $currentPrice) * 100, 2) : null;

                return [
                    'id' => $hp->id,
                    'item_name' => $hp->item_name,
                    'brand' => $hp->brand,
                    'category' => $hp->category,
                    'specification' => $hp->specification,
                    'unit' => $hp->unit,
                    'price' => $hp->price,
                    'currency' => $hp->currency,
                    'supplier' => $hp->supplier,
                    'location' => $hp->location,
                    'source_reference' => $hp->source_reference,
                    'fetched_at' => $hp->fetched_at->format('Y-m-d H:i'),
                    'similarity_score' => round($match['similarity_score'] * 100),
                    'match_reasons' => $match['match_reasons'],
                    'variance_percent' => $variance,
                    'price_history' => $this->getPriceHistorySummary($hp),
                ];
            })->toArray(),
        ];
    }

    private function getPriceHistorySummary(HardwarePrice $price): array
    {
        $histories = $price->priceHistories()->orderBy('recorded_at')->get();

        if ($histories->isEmpty()) {
            return [
                'records' => 1,
                'lowest' => $price->price,
                'highest' => $price->price,
                'average' => $price->price,
                'change' => 0,
                'change_percent' => 0,
                'trend' => 'stable',
            ];
        }

        $prices = $histories->pluck('price')->toArray();
        $prices[] = $price->price;

        $lowest = min($prices);
        $highest = max($prices);
        $average = array_sum($prices) / count($prices);
        $first = $histories->first()->price;
        $last = $price->price;
        $change = $last - $first;
        $changePercent = $first > 0 ? round(($change / $first) * 100, 2) : 0;

        $trend = 'stable';
        if ($changePercent > 5) {
            $trend = 'rising';
        } elseif ($changePercent < -5) {
            $trend = 'falling';
        }

        return [
            'records' => count($prices),
            'lowest' => $lowest,
            'highest' => $highest,
            'average' => round($average, 2),
            'change' => round($change, 2),
            'change_percent' => $changePercent,
            'trend' => $trend,
        ];
    }
}
