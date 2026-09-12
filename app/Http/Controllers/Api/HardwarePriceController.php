<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HardwarePrice;
use App\Models\PriceHistory;
use App\Services\HardwarePriceFetchingService;
use App\Services\PriceMatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HardwarePriceController extends Controller
{
    public function __construct(
        private HardwarePriceFetchingService $fetchingService,
        private PriceMatchingService $matchingService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = HardwarePrice::active()
            ->where('organisation_id', $request->user()->organisation_id)
            ->with('organisation');

        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }

        if ($request->filled('supplier')) {
            $query->bySupplier($request->supplier);
        }

        if ($request->filled('location')) {
            $query->byLocation($request->location);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        if ($request->filled('date_from')) {
            $query->where('fetched_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('fetched_at', '<=', $request->date_to);
        }

        $sortBy = $request->get('sort_by', 'fetched_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $perPage = min($request->get('per_page', 20), 100);
        $prices = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $prices,
        ]);
    }

    public function show(Request $request, HardwarePrice $hardwarePrice): JsonResponse
    {
        abort_unless($hardwarePrice->organisation_id === $request->user()->organisation_id, 403);

        $hardwarePrice->load(['priceHistories' => fn ($q) => $q->orderBy('recorded_at', 'desc')->limit(50)]);

        return response()->json([
            'success' => true,
            'data' => $hardwarePrice,
        ]);
    }

    public function history(Request $request, HardwarePrice $hardwarePrice): JsonResponse
    {
        abort_unless($hardwarePrice->organisation_id === $request->user()->organisation_id, 403);

        $histories = PriceHistory::where('organisation_id', $request->user()->organisation_id)
            ->where('hardware_price_id', $hardwarePrice->id)
            ->orderBy('recorded_at', 'desc')
            ->paginate($request->get('per_page', 50));

        $summary = [
            'current_price' => $hardwarePrice->price,
            'currency' => $hardwarePrice->currency,
            'lowest_price' => $hardwarePrice->lowest_price,
            'highest_price' => $hardwarePrice->highest_price,
            'average_price' => $hardwarePrice->average_price,
            'price_change' => $hardwarePrice->price_change,
            'price_change_percent' => $hardwarePrice->price_change_percent,
            'total_records' => $hardwarePrice->priceHistories()->count() + 1,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'item' => $hardwarePrice,
                'summary' => $summary,
                'history' => $histories,
            ],
        ]);
    }

    public function compare(Request $request): JsonResponse
    {
        $ids = $request->get('ids', []);
        
        if (!is_array($ids) || count($ids) < 2 || count($ids) > 10) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide 2 to 10 item IDs to compare',
            ], 422);
        }

        $items = HardwarePrice::active()
            ->where('organisation_id', $request->user()->organisation_id)
            ->whereIn('id', $ids)
            ->with('priceHistories')
            ->get();

        if ($items->count() !== count($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'One or more items not found',
            ], 404);
        }

        $comparison = $items->map(function ($item) {
            $histories = $item->priceHistories()->orderBy('recorded_at')->get();
            $prices = $histories->pluck('price')->toArray();
            $prices[] = $item->price;

            return [
                'id' => $item->id,
                'item_name' => $item->item_name,
                'brand' => $item->brand,
                'category' => $item->category,
                'specification' => $item->specification,
                'unit' => $item->unit,
                'price' => $item->price,
                'currency' => $item->currency,
                'supplier' => $item->supplier,
                'location' => $item->location,
                'source_reference' => $item->source_reference,
                'fetched_at' => $item->fetched_at->format('Y-m-d H:i'),
                'price_history' => [
                    'records' => count($prices),
                    'lowest' => min($prices),
                    'highest' => max($prices),
                    'average' => round(array_sum($prices) / count($prices), 2),
                    'change' => count($prices) > 1 ? round($prices[count($prices)-1] - $prices[0], 2) : 0,
                    'change_percent' => count($prices) > 1 && $prices[0] > 0 
                        ? round((($prices[count($prices)-1] - $prices[0]) / $prices[0]) * 100, 2) 
                        : 0,
                    'trend' => $this->calculateTrend($prices),
                ],
                'rating' => $this->calculateRating($item, $prices),
            ];
        });

        $bestValue = $comparison->sortBy(fn ($i) => $i['rating']['value_score'])->first();
        $lowestPrice = $comparison->sortBy('price')->first();
        $bestRated = $comparison->sortByDesc(fn ($i) => $i['rating']['overall'])->first();

        foreach ($comparison as $item) {
            $item['badges'] = [];
            if ($item['id'] === $bestValue['id']) $item['badges'][] = 'Best Value';
            if ($item['id'] === $lowestPrice['id']) $item['badges'][] = 'Lowest Price';
            if ($item['id'] === $bestRated['id']) $item['badges'][] = 'Best Rated';
        }

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $comparison,
                'summary' => [
                    'best_value' => $bestValue['id'],
                    'lowest_price' => $lowestPrice['id'],
                    'best_rated' => $bestRated['id'],
                ],
            ],
        ]);
    }

    public function recommendations(Request $request): JsonResponse
    {
        $category = $request->get('category');
        $location = $request->get('location');
        $limit = min($request->get('limit', 10), 50);

        $query = HardwarePrice::active()
            ->where('organisation_id', $request->user()->organisation_id);

        if ($category) {
            $query->byCategory($category);
        }

        if ($location) {
            $query->byLocation($location);
        }

        $items = $query->with('priceHistories')->get();

        $recommendations = $items->map(function ($item) {
            $histories = $item->priceHistories()->orderBy('recorded_at')->get();
            $prices = $histories->pluck('price')->toArray();
            $prices[] = $item->price;

            $rating = $this->calculateRating($item, $prices);

            return [
                'id' => $item->id,
                'item_name' => $item->item_name,
                'brand' => $item->brand,
                'category' => $item->category,
                'specification' => $item->specification,
                'unit' => $item->unit,
                'price' => $item->price,
                'currency' => $item->currency,
                'supplier' => $item->supplier,
                'location' => $item->location,
                'source_reference' => $item->source_reference,
                'fetched_at' => $item->fetched_at->format('Y-m-d H:i'),
                'rating' => $rating,
                'price_history' => [
                    'records' => count($prices),
                    'lowest' => min($prices),
                    'highest' => max($prices),
                    'average' => round(array_sum($prices) / count($prices), 2),
                    'change' => count($prices) > 1 ? round($prices[count($prices)-1] - $prices[0], 2) : 0,
                    'change_percent' => count($prices) > 1 && $prices[0] > 0 
                        ? round((($prices[count($prices)-1] - $prices[0]) / $prices[0]) * 100, 2) 
                        : 0,
                    'trend' => $this->calculateTrend($prices),
                ],
            ];
        })
        ->sortByDesc(fn ($i) => $i['rating']['overall'])
        ->take($limit)
        ->values();

        return response()->json([
            'success' => true,
            'data' => $recommendations,
        ]);
    }

    public function matchBoqItem(Request $request, int $boqItemId): JsonResponse
    {
        $boqItem = \App\Models\BoqItem::with('boq.project')->findOrFail($boqItemId);
        
        abort_unless($boqItem->boq->project->organisation_id === $request->user()->organisation_id, 403);

        $comparison = $this->matchingService->getPriceComparison($boqItem);

        return response()->json([
            'success' => true,
            'data' => $comparison,
        ]);
    }

    public function applyPrice(Request $request, int $boqItemId): JsonResponse
    {
        $request->validate([
            'hardware_price_id' => 'required|exists:hardware_prices,id',
        ]);

        $boqItem = \App\Models\BoqItem::with('boq.project')->findOrFail($boqItemId);
        
        abort_unless($boqItem->boq->project->organisation_id === $request->user()->organisation_id, 403);

        $hardwarePrice = HardwarePrice::findOrFail($request->hardware_price_id);
        
        abort_unless($hardwarePrice->organisation_id === $request->user()->organisation_id, 403);

        $this->matchingService->applyPriceToBoqItem($boqItem, $hardwarePrice);

        return response()->json([
            'success' => true,
            'message' => 'Market price applied to BOQ item',
            'data' => $boqItem->fresh(),
        ]);
    }

    public function fetchNow(Request $request): JsonResponse
    {
        $request->user()->authorizeRoles(['admin', 'manager']);

        $results = $this->fetchingService->fetchDailyPrices();

        return response()->json([
            'success' => true,
            'message' => 'Price fetching completed',
            'data' => $results,
        ]);
    }

    public function statistics(Request $request): JsonResponse
    {
        $orgId = $request->user()->organisation_id;

        $totalItems = HardwarePrice::where('organisation_id', $orgId)->where('is_active', true)->count();
        $todayPrices = HardwarePrice::where('organisation_id', $orgId)
            ->where('is_active', true)
            ->whereDate('fetched_at', today())
            ->count();
        $suppliers = HardwarePrice::where('organisation_id', $orgId)
            ->where('is_active', true)
            ->distinct('supplier')
            ->count('supplier');
        
        $avgChange = HardwarePrice::where('organisation_id', $orgId)
            ->where('is_active', true)
            ->whereNotNull('price_change_percent')
            ->avg('price_change_percent') ?? 0;

        $lowestOpportunities = HardwarePrice::where('organisation_id', $orgId)
            ->where('is_active', true)
            ->whereRaw('price < (SELECT AVG(price) FROM hardware_prices hp2 WHERE hp2.category = hardware_prices.category AND hp2.organisation_id = ?)', [$orgId])
            ->count();

        $boqItemsWithMatches = \App\Models\BoqItem::whereHas('boq.project', fn ($q) => $q->where('organisation_id', $orgId))
            ->whereNotNull('ai_rate')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'items_tracked' => $totalItems,
                'prices_updated_today' => $todayPrices,
                'average_price_change' => round($avgChange, 2),
                'suppliers_tracked' => $suppliers,
                'lowest_price_opportunities' => $lowestOpportunities,
                'boq_items_with_updated_prices' => $boqItemsWithMatches,
            ],
        ]);
    }

    public function categories(Request $request): JsonResponse
    {
        $categories = HardwarePrice::where('organisation_id', $request->user()->organisation_id)
            ->where('is_active', true)
            ->distinct('category')
            ->pluck('category')
            ->map(fn ($c) => ['name' => $c, 'count' => HardwarePrice::where('organisation_id', $request->user()->organisation_id)->where('category', $c)->where('is_active', true)->count()])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    private function calculateTrend(array $prices): string
    {
        if (count($prices) < 2) return 'stable';
        
        $recent = array_slice($prices, -3);
        $older = array_slice($prices, 0, 3);
        
        $recentAvg = array_sum($recent) / count($recent);
        $olderAvg = array_sum($older) / count($older);
        
        if ($olderAvg == 0) return 'stable';
        
        $change = (($recentAvg - $olderAvg) / $olderAvg) * 100;
        
        if ($change > 5) return 'rising';
        if ($change < -5) return 'falling';
        return 'stable';
    }

    private function calculateRating(HardwarePrice $item, array $prices): array
    {
        $currentPrice = $item->price;
        $avgPrice = array_sum($prices) / count($prices);
        $minPrice = min($prices);
        $maxPrice = max($prices);
        $priceStability = $maxPrice > 0 ? 1 - (($maxPrice - $minPrice) / $maxPrice) : 1;
        $dataFreshness = $item->fetched_at->diffInDays(now()) <= 7 ? 1 : max(0, 1 - ($item->fetched_at->diffInDays(now()) / 30));
        
        $priceScore = $currentPrice <= $avgPrice ? 100 : max(0, 100 - (($currentPrice - $avgPrice) / $avgPrice) * 50);
        $stabilityScore = $priceStability * 100;
        $freshnessScore = $dataFreshness * 100;
        $supplierScore = 75;
        $availabilityScore = 80;

        $overall = round(
            ($priceScore * 0.35) +
            ($stabilityScore * 0.25) +
            ($freshnessScore * 0.20) +
            ($supplierScore * 0.10) +
            ($availabilityScore * 0.10)
        );

        return [
            'overall' => $overall,
            'value_score' => round($priceScore),
            'stability_score' => round($stabilityScore),
            'freshness_score' => round($freshnessScore),
            'supplier_score' => $supplierScore,
            'availability_score' => $availabilityScore,
            'factors' => [
                'price_vs_average' => $currentPrice <= $avgPrice ? 'Below average price' : 'Above average price',
                'price_stability' => $priceStability > 0.8 ? 'Very stable' : ($priceStability > 0.5 ? 'Moderately stable' : 'Volatile'),
                'data_freshness' => $dataFreshness > 0.8 ? 'Very recent' : ($dataFreshness > 0.5 ? 'Recent' : 'Outdated'),
                'supplier_reliability' => 'Assumed reliable',
                'availability' => 'Good availability',
            ],
        ];
    }
}