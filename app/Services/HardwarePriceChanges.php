<?php

namespace App\Services;

use App\Models\HardwarePrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Recent price changes across every item a user can see (general market prices
 * and their organisation's own). Shared by the Price Changes page and the API so
 * both list and count the same items.
 */
class HardwarePriceChanges
{
    public const PERIODS = ['7', '30', '90', '365', 'all'];

    public const DIRECTIONS = ['', 'up', 'down'];

    public const SOURCES = ['', 'general', 'mine'];

    public const SORTS = ['recent', 'up', 'down'];

    /**
     * Valid filters with defaults; unknown values fall back to the default.
     *
     * @return array{search: string, period: string, direction: string, category: string, source: string, sort: string}
     */
    public static function filters(array $input): array
    {
        $pick = fn (string $key, array $allowed, string $default) => in_array((string) ($input[$key] ?? $default), $allowed, true)
            ? (string) ($input[$key] ?? $default)
            : $default;

        return [
            'search' => trim((string) ($input['search'] ?? '')),
            'period' => $pick('period', self::PERIODS, '30'),
            'direction' => $pick('direction', self::DIRECTIONS, ''),
            'category' => trim((string) ($input['category'] ?? '')),
            'source' => $pick('source', self::SOURCES, ''),
            'sort' => $pick('sort', self::SORTS, 'recent'),
        ];
    }

    /** Changed, active prices matching every filter except direction (the stats count both ways). */
    public function base(?int $organisationId, array $filters): Builder
    {
        return HardwarePrice::query()
            ->visibleTo($organisationId)
            ->where('is_active', true)
            ->whereNotNull('price_change')
            ->where('price_change', '!=', 0)
            ->when($filters['period'] !== 'all', fn ($q) => $q->where('price_changed_at', '>=', now()->subDays((int) $filters['period'])))
            ->when($filters['source'] === 'general', fn ($q) => $q->whereNull('organisation_id'))
            ->when($filters['source'] === 'mine' && $organisationId !== null, fn ($q) => $q->where('organisation_id', $organisationId))
            ->when($filters['category'] !== '', fn ($q) => $q->where('category', $filters['category']))
            ->when($filters['search'] !== '', fn ($q) => $q->search($filters['search']));
    }

    /** The listed changes: direction applied and sorted. */
    public function list(Builder $base, array $filters): Builder
    {
        return (clone $base)
            ->when($filters['direction'] === 'up', fn ($q) => $q->where('price_change', '>', 0))
            ->when($filters['direction'] === 'down', fn ($q) => $q->where('price_change', '<', 0))
            ->when($filters['sort'] === 'recent', fn ($q) => $q->orderByDesc('price_changed_at'))
            ->when($filters['sort'] === 'up', fn ($q) => $q->orderByDesc('price_change_percent'))
            ->when($filters['sort'] === 'down', fn ($q) => $q->orderBy('price_change_percent'))
            ->orderByDesc('id');
    }

    /** @return array{total: int, up: int, down: int, average: ?float} */
    public function stats(Builder $base): array
    {
        $average = (clone $base)->avg('price_change_percent');

        return [
            'total' => (clone $base)->count(),
            'up' => (clone $base)->where('price_change', '>', 0)->count(),
            'down' => (clone $base)->where('price_change', '<', 0)->count(),
            'average' => $average !== null ? round((float) $average, 2) : null,
        ];
    }

    /** Categories that have at least one change, for the category filter. */
    public function categories(?int $organisationId): Collection
    {
        return HardwarePrice::query()
            ->visibleTo($organisationId)
            ->whereNotNull('price_change')
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');
    }
}
