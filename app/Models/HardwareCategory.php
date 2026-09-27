<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HardwareCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'default_items',
        'is_active',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'default_items' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The hardware_items table is the source of truth for a category's items. Admin
     * forms and the API still submit a simple list (default_items), which is synced
     * into the table whenever it changes.
     */
    protected static function booted(): void
    {
        static::saved(function (HardwareCategory $category): void {
            if ($category->wasRecentlyCreated || $category->wasChanged('default_items')) {
                $category->syncItems($category->default_items ?? []);
            }
        });
    }

    public function hardwarePrices(): HasMany
    {
        return $this->hasMany(HardwarePrice::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(HardwareItem::class)->orderBy('sort_order')->orderBy('name');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Active item names for this category, in display order.
     *
     * @return list<string>
     */
    public function itemNames(): array
    {
        return $this->items()->where('is_active', true)->pluck('name')->all();
    }

    /**
     * Make the item table match the given list: add or reactivate listed items and
     * deactivate the rest (items are referenced by name on prices, so never deleted).
     *
     * @param  array<int, mixed>  $names
     */
    public function syncItems(array $names): void
    {
        if (! Schema::hasTable('hardware_items')) {
            return;
        }

        self::syncItemsFor((int) $this->getKey(), $names);
    }

    /**
     * Backfill hardware_items from every category's default_items list.
     */
    public static function syncAllItemsFromDefaults(): void
    {
        if (! Schema::hasTable('hardware_items')) {
            return;
        }

        DB::table('hardware_categories')->select(['id', 'default_items'])->orderBy('id')->each(function ($row): void {
            $names = json_decode((string) $row->default_items, true);

            if (is_array($names) && $names !== []) {
                self::syncItemsFor((int) $row->id, $names, deactivateMissing: false);
            }
        });
    }

    /**
     * @param  array<int, mixed>  $names
     */
    private static function syncItemsFor(int $categoryId, array $names, bool $deactivateMissing = true): void
    {
        $names = collect($names)
            ->filter(fn ($name) => is_string($name) && trim($name) !== '')
            ->map(fn (string $name) => mb_substr(trim($name), 0, 191))
            ->unique(fn (string $name) => mb_strtolower($name))
            ->values();

        $now = now();

        foreach ($names as $index => $name) {
            DB::table('hardware_items')->updateOrInsert(
                ['hardware_category_id' => $categoryId, 'name' => $name],
                ['is_active' => true, 'sort_order' => ($index + 1) * 10, 'updated_at' => $now, 'created_at' => $now],
            );
        }

        if ($deactivateMissing) {
            DB::table('hardware_items')
                ->where('hardware_category_id', $categoryId)
                ->whereNotIn('name', $names->all())
                ->update(['is_active' => false, 'updated_at' => $now]);
        }
    }
}
