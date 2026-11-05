<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Boq;
use App\Models\HardwarePrice;
use App\Models\PlanPriceChange;
use App\Services\BoqPriceCheck;
use App\Services\HardwarePriceChanges;
use App\Services\PlanPriceHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Price history and comparison endpoints for the mobile app. Each one uses the
 * same service as its web page, so the app and the website agree.
 */
class PriceInsightsController extends Controller
{
    /** GET hardware-prices/{hardwarePrice}/monthly?months=12 */
    public function monthly(Request $request, HardwarePrice $hardwarePrice): JsonResponse
    {
        abort_unless($hardwarePrice->isVisibleTo($request->user()->organisation_id), 403);

        $months = in_array($request->integer('months', 12), [6, 12, 24], true) ? $request->integer('months', 12) : 12;

        return response()->json([
            'success' => true,
            'data' => [
                'item' => $hardwarePrice,
                'months' => $hardwarePrice->monthlyHistory($months)->map(fn (array $row) => [
                    'month' => $row['month']->format('Y-m'),
                    'records' => $row['records'],
                    'low' => $row['low'],
                    'high' => $row['high'],
                    'average' => $row['average'] !== null ? round($row['average'], 2) : null,
                    'opening' => $row['opening'],
                    'closing' => $row['closing'],
                    'change' => $row['change'] !== null ? round($row['change'], 2) : null,
                    'change_percent' => $row['change_percent'] !== null ? round($row['change_percent'], 2) : null,
                ])->values(),
            ],
        ]);
    }

    /** GET hardware-prices/changes?period=30&direction=&category=&source=&sort=recent&search=&page=&per_page= */
    public function changes(Request $request, HardwarePriceChanges $changes): JsonResponse
    {
        $organisationId = $request->user()->organisation_id;
        $filters = HardwarePriceChanges::filters($request->only(['search', 'period', 'direction', 'category', 'source', 'sort']));
        $base = $changes->base($organisationId, $filters);
        $perPage = min(max($request->integer('per_page', 20), 1), 100);

        return response()->json([
            'success' => true,
            'data' => $changes->list($base, $filters)->paginate($perPage),
            'meta' => [
                'filters' => $filters,
                'stats' => $changes->stats($base),
                'categories' => $changes->categories($organisationId),
            ],
        ]);
    }

    /** GET boqs/{boq}/price-check?filter=all|above|below|changed */
    public function boqPriceCheck(Request $request, Boq $boq, BoqPriceCheck $priceCheck): JsonResponse
    {
        abort_unless($request->user()->can('view', $boq), 403);

        $filter = in_array($request->input('filter'), BoqPriceCheck::FILTERS, true) ? $request->input('filter') : 'all';
        $all = $priceCheck->rows($boq, $request->user()->organisation_id);

        return response()->json([
            'success' => true,
            'data' => [
                'currency' => $boq->currency,
                'filter' => $filter,
                'summary' => $priceCheck->summary($boq, $all),
                'rows' => $priceCheck->filter($all, $filter)->map(fn (array $row) => [
                    'boq_item_id' => $row['item']->id,
                    'item_code' => $row['item']->item_code,
                    'description' => $row['item']->description,
                    'quantity' => (float) $row['item']->quantity,
                    'unit' => $row['item']->unit,
                    'currency' => $row['item']->currency,
                    'rate' => $row['rate'],
                    'rate_field' => $row['rate_field'],
                    'comparable' => $row['comparable'],
                    'difference' => $row['difference'],
                    'percent' => $row['percent'],
                    'since_match' => $row['since_match'],
                    'market' => [
                        'id' => $row['market']->id,
                        'item_name' => $row['market']->item_name,
                        'price' => (float) $row['market']->price,
                        'currency' => $row['market']->currency,
                        'unit' => $row['market']->unit,
                        'supplier' => $row['market']->supplier,
                        'fetched_at' => $row['market']->fetched_at?->toIso8601String(),
                    ],
                ])->values(),
            ],
        ]);
    }

    /** GET plans/price-history?scope=mine|all */
    public function planPriceHistory(Request $request, PlanPriceHistory $history): JsonResponse
    {
        $scope = in_array($request->input('scope'), PlanPriceHistory::SCOPES, true) ? $request->input('scope') : 'mine';
        $subscribedPlanIds = $history->subscribedPlanIds($request->user());
        $page = $history->query($subscribedPlanIds, $scope)->paginate(min(max($request->integer('per_page', 20), 1), 100));

        $page->getCollection()->transform(fn (PlanPriceChange $change) => [
            'id' => $change->id,
            'plan' => $change->plan ? ['id' => $change->plan->id, 'name' => $change->plan->name, 'type' => $change->plan->type] : null,
            'old_price' => $change->old_price !== null ? (float) $change->old_price : null,
            'new_price' => (float) $change->new_price,
            'old_currency' => $change->old_currency,
            'new_currency' => $change->new_currency,
            'difference' => $change->difference(),
            'percent_change' => $change->percentChange(),
            'changed_at' => $change->changed_at?->toIso8601String(),
            'is_my_plan' => in_array($change->plan_id, $subscribedPlanIds, true),
        ]);

        return response()->json([
            'success' => true,
            'data' => $page,
            'meta' => ['scope' => $scope],
        ]);
    }
}
