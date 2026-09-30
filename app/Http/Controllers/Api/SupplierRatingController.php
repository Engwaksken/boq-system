<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierRating;
use App\Services\SupplierRatings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Supplier and factory ratings for the mobile app: top 10 rankings, the chart
 * figures, a supplier's performance, and rating a supplier.
 */
class SupplierRatingController extends Controller
{
    public function __construct(private SupplierRatings $ratings) {}

    /** GET supplier-ratings/leaderboard?period=week|month|year|all&type=supplier|factory&limit=10&order=top|lowest */
    public function leaderboard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['nullable', Rule::in(SupplierRatings::PERIODS)],
            'type' => ['nullable', Rule::in([Supplier::TYPE_SUPPLIER, Supplier::TYPE_FACTORY])],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'order' => ['nullable', Rule::in(['top', 'lowest'])],
        ]);
        $period = $data['period'] ?? 'month';
        $type = $data['type'] ?? Supplier::TYPE_SUPPLIER;

        $rows = $this->ratings->leaderboard($period, $type, (int) ($data['limit'] ?? 10), ($data['order'] ?? 'top') === 'lowest');

        return response()->json(['success' => true, 'data' => [
            'period' => $period,
            'period_label' => SupplierRatings::periodLabel($period),
            'since' => SupplierRatings::since($period)?->toIso8601String(),
            'type' => $type,
            'items' => array_map(fn (array $row) => [
                'rank' => $row['rank'],
                'supplier' => $this->supplier($row['supplier']),
                'average' => $row['average'],
                'score' => $row['score'],
                'count' => $row['count'],
                'criteria' => $this->criteria($row['criteria']),
            ], $rows),
        ]]);
    }

    /** GET supplier-ratings/summary?period=: totals, distribution, by type, scores by area and 12-month trends. */
    public function summary(Request $request): JsonResponse
    {
        $period = $request->validate(['period' => ['nullable', Rule::in(SupplierRatings::PERIODS)]])['period'] ?? 'month';
        $summary = $this->ratings->summary($period);

        return response()->json(['success' => true, 'data' => [
            'period' => $period,
            'period_label' => SupplierRatings::periodLabel($period),
            'total' => $summary['total'],
            'suppliers' => $summary['suppliers'],
            'average' => $summary['average'],
            'distribution' => $this->distribution($summary['distribution']),
            'by_type' => $summary['by_type'],
            'criteria' => $this->criteria($summary['criteria']),
            'trend' => $summary['trend'],
            'trend_by_type' => [
                Supplier::TYPE_SUPPLIER => $this->ratings->trend(type: Supplier::TYPE_SUPPLIER),
                Supplier::TYPE_FACTORY => $this->ratings->trend(type: Supplier::TYPE_FACTORY),
            ],
        ]]);
    }

    /** GET supplier-ratings/suppliers?search=&type=: active suppliers to pick one to rate. */
    public function suppliers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'type' => ['nullable', Rule::in([Supplier::TYPE_SUPPLIER, Supplier::TYPE_FACTORY])],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $search = trim((string) ($data['search'] ?? ''));

        $suppliers = Supplier::where('is_active', true)
            ->when($data['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('location', 'like', "%{$search}%")->orWhere('region', 'like', "%{$search}%")))
            ->orderByDesc('ratings_count')->orderBy('name')
            ->limit((int) ($data['limit'] ?? 20))
            ->get();

        return response()->json(['success' => true, 'data' => $suppliers->map(fn (Supplier $s) => $this->supplier($s))->values()]);
    }

    /** GET supplier-ratings/suppliers/{supplier}?period=: one supplier's performance and the user's rating this month. */
    public function show(Request $request, Supplier $supplier): JsonResponse
    {
        $period = $request->validate(['period' => ['nullable', Rule::in(SupplierRatings::PERIODS)]])['period'] ?? 'month';
        $inPeriod = $this->ratings->summary($period, $supplier);
        $allTime = $this->ratings->summary('all', $supplier);
        $isAdmin = (bool) $request->user()?->isSuperAdmin();
        $mine = $this->ratings->current($supplier, $request->user());

        return response()->json(['success' => true, 'data' => [
            'supplier' => $this->supplier($supplier),
            'period' => $period,
            'rank' => $this->ratings->rankOf($supplier, $period),
            'period_total' => $inPeriod['total'],
            'period_average' => $inPeriod['average'],
            'total' => $allTime['total'],
            'average' => $allTime['average'],
            'distribution' => $this->distribution($allTime['distribution']),
            'criteria' => $this->criteria($allTime['criteria']),
            'trend' => $allTime['trend'],
            'reviews' => $this->ratings->recentReviews($supplier, 10, $isAdmin)->map(fn (SupplierRating $r) => [
                'id' => $r->id,
                'rating' => $r->rating,
                'comment' => $r->comment,
                'author' => $isAdmin ? $r->user?->name : null,
                'is_hidden' => $r->is_hidden,
                'rated_at' => $r->rated_at?->toIso8601String(),
            ])->values(),
            'mine' => $mine ? $this->rating($mine) : null,
        ]]);
    }

    /** POST supplier-ratings/suppliers/{supplier}: rate (replaces the user's rating this month). */
    public function store(Request $request, Supplier $supplier): JsonResponse
    {
        abort_unless($supplier->is_active, 422, 'This supplier is not active.');
        $star = ['nullable', 'integer', 'min:1', 'max:5'];
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'price_rating' => $star,
            'quality_rating' => $star,
            'delivery_rating' => $star,
            'service_rating' => $star,
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $rating = $this->ratings->rate($supplier, $request->user(), $data);

        return response()->json([
            'success' => true,
            'message' => __('Thank you. Your rating of :name was saved.', ['name' => $supplier->name]),
            'data' => $this->rating($rating) + ['supplier' => $this->supplier($supplier->fresh())],
        ], $rating->wasRecentlyCreated ? 201 : 200);
    }

    /** GET supplier-ratings/mine: the user's latest ratings. */
    public function mine(Request $request): JsonResponse
    {
        $ratings = SupplierRating::with('supplier')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('rated_at')
            ->limit(50)
            ->get();

        return response()->json(['success' => true, 'data' => $ratings->map(fn (SupplierRating $r) => $this->rating($r) + [
            'supplier' => $r->supplier ? $this->supplier($r->supplier) : null,
        ])->values()]);
    }

    /** PATCH supplier-ratings/{rating}/visibility {hidden: bool} (super admins). */
    public function visibility(Request $request, SupplierRating $rating): JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $hidden = $request->validate(['hidden' => ['required', 'boolean']])['hidden'];
        $this->ratings->setHidden($rating, (bool) $hidden);

        return response()->json(['success' => true, 'data' => ['id' => $rating->id, 'is_hidden' => (bool) $hidden]]);
    }

    private function supplier(Supplier $supplier): array
    {
        return [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'type' => $supplier->type,
            'location' => $supplier->location,
            'region' => $supplier->region,
            'country' => $supplier->country,
            'website_url' => $supplier->website_url,
            'rating' => (float) $supplier->rating,
            'ratings_count' => (int) $supplier->ratings_count,
        ];
    }

    private function rating(SupplierRating $rating): array
    {
        return [
            'id' => $rating->id,
            'supplier_id' => $rating->supplier_id,
            'rating' => $rating->rating,
            'price_rating' => $rating->price_rating,
            'quality_rating' => $rating->quality_rating,
            'delivery_rating' => $rating->delivery_rating,
            'service_rating' => $rating->service_rating,
            'comment' => $rating->comment,
            'period' => $rating->period,
            'rated_at' => $rating->rated_at?->toIso8601String(),
        ];
    }

    /** price_rating => price, ... */
    private function criteria(array $criteria): array
    {
        return collect($criteria)->mapWithKeys(fn ($v, $k) => [str_replace('_rating', '', $k) => $v])->all();
    }

    /** list of {stars, count}, 5 down to 1 */
    private function distribution(array $distribution): array
    {
        return collect($distribution)->map(fn ($count, $stars) => ['stars' => (int) $stars, 'count' => (int) $count])->values()->all();
    }
}
