<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\SupplierRating;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * User ratings of hardware suppliers and factories: saving a rating, the top
 * rated rankings for a week, month or year, and the figures for the charts.
 *
 * Rankings use a weighted (Bayesian) score so a supplier with one 5-star
 * rating does not outrank one with forty 4.8-star ratings.
 */
class SupplierRatings
{
    public const PERIODS = ['week', 'month', 'year', 'all'];

    /** Ratings a supplier needs before its average counts fully in the ranking. */
    private const CONFIDENCE = 3;

    /** Start of a period (rolling: last 7 days, 30 days, 12 months), null = all time. */
    public static function since(string $period): ?Carbon
    {
        return match ($period) {
            'week' => now()->subDays(7)->startOfDay(),
            'month' => now()->subDays(30)->startOfDay(),
            'year' => now()->subYear()->startOfDay(),
            default => null,
        };
    }

    public static function periodLabel(string $period): string
    {
        return match ($period) {
            'week' => __('This week'),
            'month' => __('This month'),
            'year' => __('This year'),
            default => __('All time'),
        };
    }

    /**
     * Save the user's rating for this month (a second rating in the same month
     * replaces the first) and refresh the supplier's average.
     *
     * @param  array{rating: int, price_rating?: ?int, quality_rating?: ?int, delivery_rating?: ?int, service_rating?: ?int, comment?: ?string}  $data
     */
    public function rate(Supplier $supplier, User $user, array $data): SupplierRating
    {
        $values = ['rating' => (int) $data['rating']];
        foreach (array_keys(SupplierRating::CRITERIA) as $field) {
            $values[$field] = isset($data[$field]) && $data[$field] !== '' && $data[$field] !== null ? (int) $data[$field] : null;
        }
        $values['comment'] = filled($data['comment'] ?? null) ? trim((string) $data['comment']) : null;

        $rating = SupplierRating::updateOrCreate(
            ['supplier_id' => $supplier->id, 'user_id' => $user->id, 'period' => now()->format('Y-m')],
            $values + ['organisation_id' => $user->organisation_id, 'rated_at' => now()],
        );

        $this->refresh($supplier);

        return $rating;
    }

    /** Hide or show a rating (moderation), then refresh the supplier's average. */
    public function setHidden(SupplierRating $rating, bool $hidden): void
    {
        $rating->update(['is_hidden' => $hidden]);
        $this->refresh($rating->supplier);
    }

    /** The user's rating this month, if any (pre-fills the form). */
    public function current(Supplier $supplier, User $user): ?SupplierRating
    {
        return SupplierRating::where('supplier_id', $supplier->id)
            ->where('user_id', $user->id)
            ->where('period', now()->format('Y-m'))
            ->first();
    }

    /**
     * Supplier average = each user's latest rating, so frequent raters do not
     * count more than others. Stored on the supplier for lists and sorting.
     */
    public function refresh(Supplier $supplier): void
    {
        $latest = SupplierRating::visible()
            ->where('supplier_id', $supplier->id)
            ->orderByDesc('rated_at')
            ->get(['user_id', 'rating'])
            ->unique('user_id');

        $supplier->forceFill([
            'rating' => $latest->isEmpty() ? 0 : round($latest->avg('rating'), 2),
            'ratings_count' => $latest->count(),
        ])->saveQuietly();
    }

    /**
     * Top rated suppliers or factories in a period.
     *
     * @return list<array{rank: int, supplier: Supplier, average: float, score: float, count: int, criteria: array<string, ?float>}>
     */
    public function leaderboard(string $period, string $type, int $limit = 10, bool $lowest = false): array
    {
        $rows = $this->ranked($period, $type);
        $rows = $lowest ? $rows->sortBy([['score', 'asc'], ['count', 'desc']]) : $rows->sortBy([['score', 'desc'], ['count', 'desc']]);

        return $rows->take($limit)->values()->map(fn (array $row, int $i) => ['rank' => $i + 1] + $row)->all();
    }

    /** Where a supplier stands among its type in the period (null = not rated). */
    public function rankOf(Supplier $supplier, string $period): ?array
    {
        $rows = $this->ranked($period, $supplier->type)->sortBy([['score', 'desc'], ['count', 'desc']])->values();
        $index = $rows->search(fn (array $row) => $row['supplier']->id === $supplier->id);

        return $index === false ? null : ['rank' => $index + 1, 'of' => $rows->count()];
    }

    /**
     * Figures for the charts and statistics cards.
     *
     * @return array{total: int, suppliers: int, average: ?float, distribution: array<int, int>, by_type: array<string, array{count: int, average: ?float}>, criteria: array<string, ?float>, trend: list<array{period: string, label: string, average: ?float, count: int}>}
     */
    public function summary(string $period, ?Supplier $supplier = null): array
    {
        $base = fn () => $this->ratingsQuery($period)->when($supplier, fn ($q) => $q->where('supplier_ratings.supplier_id', $supplier->id));

        $totals = $base()->selectRaw('count(*) as total, count(distinct supplier_ratings.supplier_id) as suppliers, avg(supplier_ratings.rating) as average')->first();

        $distribution = array_fill_keys([5, 4, 3, 2, 1], 0);
        foreach ($base()->selectRaw('supplier_ratings.rating as stars, count(*) as total')->groupBy('supplier_ratings.rating')->get() as $row) {
            $distribution[(int) $row->stars] = (int) $row->total;
        }

        $byType = [];
        foreach ([Supplier::TYPE_SUPPLIER, Supplier::TYPE_FACTORY] as $type) {
            $row = $base()->where('suppliers.type', $type)->selectRaw('count(*) as total, avg(supplier_ratings.rating) as average')->first();
            $byType[$type] = ['count' => (int) $row->total, 'average' => $row->average !== null ? round((float) $row->average, 2) : null];
        }

        $criteria = [];
        $averages = $base()->selectRaw(collect(SupplierRating::CRITERIA)->keys()->map(fn ($f) => "avg(supplier_ratings.{$f}) as {$f}")->implode(', '))->first();
        foreach (array_keys(SupplierRating::CRITERIA) as $field) {
            $criteria[$field] = $averages->{$field} !== null ? round((float) $averages->{$field}, 2) : null;
        }

        return [
            'total' => (int) $totals->total,
            'suppliers' => (int) $totals->suppliers,
            'average' => $totals->average !== null ? round((float) $totals->average, 2) : null,
            'distribution' => $distribution,
            'by_type' => $byType,
            'criteria' => $criteria,
            'trend' => $this->trend($supplier),
        ];
    }

    /**
     * Monthly average and number of ratings for the last 12 months.
     *
     * @return list<array{period: string, label: string, average: ?float, count: int}>
     */
    public function trend(?Supplier $supplier = null, ?string $type = null, int $months = 12): array
    {
        $first = now()->startOfMonth()->subMonths($months - 1);
        $rows = SupplierRating::visible()
            ->join('suppliers', 'suppliers.id', '=', 'supplier_ratings.supplier_id')
            ->where('supplier_ratings.period', '>=', $first->format('Y-m'))
            ->when($supplier, fn ($q) => $q->where('supplier_ratings.supplier_id', $supplier->id))
            ->when($type, fn ($q) => $q->where('suppliers.type', $type))
            ->selectRaw('supplier_ratings.period as month, avg(supplier_ratings.rating) as average, count(*) as total')
            ->groupBy('supplier_ratings.period')
            ->get()
            ->keyBy('month');

        $trend = [];
        for ($i = 0; $i < $months; $i++) {
            $month = $first->copy()->addMonths($i);
            $row = $rows->get($month->format('Y-m'));
            $trend[] = [
                'period' => $month->format('Y-m'),
                'label' => $month->translatedFormat('M Y'),
                'average' => $row ? round((float) $row->average, 2) : null,
                'count' => $row ? (int) $row->total : 0,
            ];
        }

        return $trend;
    }

    /** Latest visible reviews with a comment. */
    public function recentReviews(Supplier $supplier, int $limit = 8, bool $includeHidden = false): Collection
    {
        return SupplierRating::with('user:id,name')
            ->where('supplier_id', $supplier->id)
            ->when(! $includeHidden, fn ($q) => $q->visible())
            ->orderByDesc('rated_at')
            ->limit($limit)
            ->get();
    }

    /** Rated suppliers of a type in the period with their averages and weighted score. */
    private function ranked(string $period, string $type): Collection
    {
        $criteria = collect(SupplierRating::CRITERIA)->keys()->map(fn ($f) => "avg(supplier_ratings.{$f}) as {$f}")->implode(', ');

        $rows = $this->ratingsQuery($period)
            ->where('suppliers.type', $type)
            ->where('suppliers.is_active', true)
            ->groupBy('supplier_ratings.supplier_id')
            ->selectRaw("supplier_ratings.supplier_id, avg(supplier_ratings.rating) as average, count(*) as total, {$criteria}")
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $overall = $rows->sum(fn ($r) => $r->average * $r->total) / max(1, $rows->sum('total'));
        $suppliers = Supplier::whereKey($rows->pluck('supplier_id'))->get()->keyBy('id');

        return $rows->map(function ($row) use ($overall, $suppliers) {
            $count = (int) $row->total;
            $average = (float) $row->average;

            return [
                'supplier' => $suppliers->get($row->supplier_id),
                'average' => round($average, 2),
                'score' => round(($average * $count + $overall * self::CONFIDENCE) / ($count + self::CONFIDENCE), 4),
                'count' => $count,
                'criteria' => collect(SupplierRating::CRITERIA)->keys()
                    ->mapWithKeys(fn ($f) => [$f => $row->{$f} !== null ? round((float) $row->{$f}, 2) : null])->all(),
            ];
        })->filter(fn ($row) => $row['supplier'] !== null)->values();
    }

    private function ratingsQuery(string $period): Builder
    {
        $since = self::since($period);

        return SupplierRating::query()
            ->where('supplier_ratings.is_hidden', false)
            ->join('suppliers', 'suppliers.id', '=', 'supplier_ratings.supplier_id')
            ->when($since, fn ($q) => $q->where('supplier_ratings.rated_at', '>=', $since));
    }
}
