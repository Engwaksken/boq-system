<?php

namespace App\Support;

use App\Models\Category;
use App\Models\HardwareCategory;

/**
 * One place to read the selectable categories from the database, so forms,
 * filters and the API never keep their own hard-coded lists.
 */
class Categories
{
    /** @return list<string> */
    public static function projectTypes(): array
    {
        return self::names(Category::TYPE_PROJECT);
    }

    /** @return list<string> */
    public static function workSections(): array
    {
        return self::names(Category::TYPE_WORK);
    }

    /**
     * Active material categories (global plus the organisation's own) with their items.
     *
     * @return list<array{id: int, name: string, description: ?string, items: list<string>}>
     */
    public static function materials(?int $organisationId = null): array
    {
        $query = HardwareCategory::active()->with(['items' => fn ($q) => $q->where('is_active', true)]);

        if (\Illuminate\Support\Facades\Schema::hasColumn('hardware_categories', 'organisation_id')) {
            $query->where(fn ($q) => $q->whereNull('organisation_id')->orWhere('organisation_id', $organisationId));
        }

        return $query->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (HardwareCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'items' => $category->items->pluck('name')->values()->all(),
            ])
            ->values()
            ->all();
    }

    /** @return list<string> */
    public static function materialNames(?int $organisationId = null): array
    {
        return array_column(self::materials($organisationId), 'name');
    }

    /** @return list<string> */
    private static function names(string $type): array
    {
        return Category::active()->ofType($type)->pluck('name')->all();
    }
}
