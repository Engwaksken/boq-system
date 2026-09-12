<?php

namespace App\Services;

use App\Models\BoqItem;
use App\Models\HardwarePrice;
use Illuminate\Support\Collection;

class PriceMatchingService
{
    public function findMatches(BoqItem $boqItem, int $limit = 5): Collection
    {
        $query = HardwarePrice::active()
            ->where('organisation_id', $boqItem->boq->project->organisation_id)
            ->where('is_active', true);

        $matches = $query->get()->map(function ($price) use ($boqItem) {
            $similarity = $this->calculateSimilarity($boqItem, $price);
            return [
                'hardware_price' => $price,
                'similarity_score' => $similarity,
                'match_reasons' => $this->getMatchReasons($boqItem, $price),
            ];
        })
        ->filter(fn ($m) => $m['similarity_score'] > 0.3)
        ->sortByDesc('similarity_score')
        ->take($limit)
        ->values();

        return $matches;
    }

    private function calculateSimilarity(BoqItem $boqItem, HardwarePrice $price): float
    {
        $score = 0.0;
        $maxScore = 0.0;

        $itemText = strtolower(trim($boqItem->description . ' ' . ($boqItem->code ?? '')));
        $priceText = strtolower(trim($price->item_name . ' ' . ($price->brand ?? '') . ' ' . ($price->specification ?? '')));

        $itemWords = array_filter(explode(' ', $itemText));
        $priceWords = array_filter(explode(' ', $priceText));

        $commonWords = array_intersect($itemWords, $priceWords);
        $totalWords = array_unique(array_merge($itemWords, $priceWords));

        if (count($totalWords) > 0) {
            $wordSimilarity = count($commonWords) / count($totalWords);
            $score += $wordSimilarity * 0.6;
            $maxScore += 0.6;
        }

        if (str_contains($itemText, strtolower($price->category)) || str_contains($priceText, strtolower($boqItem->unit ?? ''))) {
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

    private function getMatchReasons(BoqItem $boqItem, HardwarePrice $price): array
    {
        $reasons = [];

        $itemWords = array_filter(explode(' ', strtolower($boqItem->description)));
        $priceWords = array_filter(explode(' ', strtolower($price->item_name . ' ' . ($price->brand ?? '') . ' ' . ($price->specification ?? ''))));
        $common = array_intersect($itemWords, $priceWords);

        if (!empty($common)) {
            $reasons[] = 'Name match: ' . implode(', ', array_slice($common, 0, 3));
        }

        if (str_contains(strtolower($boqItem->description), strtolower($price->category))) {
            $reasons[] = 'Category match: ' . $price->category;
        }

        if ($boqItem->unit && $price->unit && $this->unitsMatch($boqItem->unit, $price->unit)) {
            $reasons[] = 'Unit match: ' . $price->unit;
        }

        if ($boqItem->boq->project->location && $price->location) {
            if (stripos($price->location, $boqItem->boq->project->location) !== false ||
                stripos($boqItem->boq->project->location, $price->location) !== false) {
                $reasons[] = 'Location match: ' . $price->location;
            }
        }

        return $reasons;
    }

    public function applyPriceToBoqItem(BoqItem $boqItem, HardwarePrice $price): void
    {
        $boqItem->update([
            'ai_rate' => $price->price,
            'ai_rate_currency' => $price->currency,
            'ai_rate_source' => $price->source_reference ?? 'Hardware Price Database',
            'ai_rate_supplier' => $price->supplier,
            'ai_rate_location' => $price->location,
            'ai_rate_fetched_at' => $price->fetched_at,
            'ai_rate_hardware_price_id' => $price->id,
        ]);

        $boqItem->recalculateAmount();
    }

    public function getPriceComparison(BoqItem $boqItem): array
    {
        $matches = $this->findMatches($boqItem, 10);

        $currentPrice = $boqItem->current_rate ?? 0;
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
            'matches' => $matches->map(function ($match) use ($currentPrice, $currency) {
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
        if ($changePercent > 5) $trend = 'rising';
        elseif ($changePercent < -5) $trend = 'falling';

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