<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HardwarePrice extends Model
{
    use HasFactory;

    public const TYPE_HARDWARE = 'hardware';

    public const TYPE_FACTORY = 'factory';

    protected $fillable = [
        'organisation_id',
        'hardware_category_id',
        'item_name',
        'brand',
        'category',
        'price_type',
        'specification',
        'unit',
        'price',
        'currency',
        'supplier',
        'supplier_id',
        'location',
        'region',
        'source_url',
        'source_reference',
        'fetched_at',
        'last_verified_at',
        'is_active',
        'ai_metadata',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'fetched_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'ai_metadata' => 'array',
    ];

    /** A price without a region takes it from its supplier or its location. */
    protected static function booted(): void
    {
        static::saving(function (self $price) {
            if (blank($price->region) && ($price->isDirty('location') || $price->isDirty('supplier_id') || ! $price->exists)) {
                $price->region = self::guessRegion($price->location, $price->supplier_id);
            }
        });
    }

    /**
     * The region of a location: the supplier's region, else the region of a
     * supplier or price already recorded at that location. A location that is
     * itself a known region name is its own region.
     */
    public static function guessRegion(?string $location, ?int $supplierId = null): ?string
    {
        if ($supplierId && filled($region = Supplier::whereKey($supplierId)->value('region'))) {
            return trim($region);
        }

        $location = trim((string) $location);
        if ($location === '') {
            return null;
        }
        $lower = mb_strtolower($location);

        $region = Supplier::whereRaw('LOWER(location) = ?', [$lower])->whereNotNull('region')->where('region', '!=', '')->value('region')
            ?? self::query()->whereRaw('LOWER(location) = ?', [$lower])->whereNotNull('region')->where('region', '!=', '')->value('region')
            ?? Supplier::whereRaw('LOWER(region) = ?', [$lower])->value('region');

        return filled($region) ? mb_substr(trim($region), 0, 100) : null;
    }

    /** Distinct regions of the given prices (for the filters). */
    public static function regionsOf(\Illuminate\Database\Eloquent\Builder $query): array
    {
        return (clone $query)->whereNotNull('region')->where('region', '!=', '')
            ->distinct()->orderBy('region')->pluck('region')->filter()->values()->all();
    }

    /**
     * Prices a viewer can use: general market prices (no organisation, kept by
     * the platform admins) plus the viewer's organisation's own prices.
     */
    public function scopeVisibleTo(\Illuminate\Database\Eloquent\Builder $query, ?int $organisationId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where(function ($inner) use ($organisationId) {
            $inner->whereNull($inner->qualifyColumn('organisation_id'));

            if ($organisationId !== null) {
                $inner->orWhere($inner->qualifyColumn('organisation_id'), $organisationId);
            }
        });
    }

    /** Prices owned (editable) by an organisation; null = the general market prices. */
    public function scopeOwnedBy(\Illuminate\Database\Eloquent\Builder $query, ?int $organisationId): \Illuminate\Database\Eloquent\Builder
    {
        return $organisationId === null
            ? $query->whereNull($query->qualifyColumn('organisation_id'))
            : $query->where($query->qualifyColumn('organisation_id'), $organisationId);
    }

    /**
     * Prices a user may edit: super admins the general prices and their own
     * organisation's, everyone else only their organisation's.
     */
    public function scopeManageableBy(\Illuminate\Database\Eloquent\Builder $query, ?User $user): \Illuminate\Database\Eloquent\Builder
    {
        if ($user?->isSuperAdmin()) {
            return $query->visibleTo($user->organisation_id);
        }

        return $query->where($query->qualifyColumn('organisation_id'), $user?->organisation_id ?? 0);
    }

    /**
     * Where a user's new or edited prices belong: super admins keep the general
     * market prices, everyone else their own organisation's.
     */
    public static function ownerOrganisationFor(?User $user): ?int
    {
        if ($user === null || $user->isSuperAdmin()) {
            return null;
        }

        return $user->organisation_id;
    }

    public function isGeneral(): bool
    {
        return $this->organisation_id === null;
    }

    public function isVisibleTo(?int $organisationId): bool
    {
        return $this->organisation_id === null || $this->organisation_id === $organisationId;
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(
            Organisation::class
        );
    }

    public function hardwareCategory(): BelongsTo
    {
        return $this->belongsTo(
            HardwareCategory::class
        );
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(
            PriceHistory::class
        );
    }

    public function boqItems(): HasMany
    {
        return $this->hasMany(
            BoqItem::class
        );
    }

    public function latestHistory(): HasMany
    {
        return $this
            ->hasMany(
                PriceHistory::class
            )
            ->latest(
                'recorded_at'
            );
    }

    public function scopeActive($query)
    {
        return $query->where(
            'is_active',
            true
        );
    }

    public function scopeByPriceType(
        $query,
        string $priceType
    ) {
        return $query->where(
            'price_type',
            $priceType
        );
    }

    public function scopeByCategory(
        $query,
        string|int $category
    ) {
        if (is_numeric($category)) {
            return $query->where(
                'hardware_category_id',
                (int) $category
            );
        }

        return $query->where(
            'category',
            $category
        );
    }

    public function scopeBySupplier(
        $query,
        string $supplier
    ) {
        return $query->where(
            'supplier',
            $supplier
        );
    }

    public function scopeByLocation(
        $query,
        string $location
    ) {
        // A region name (e.g. "Central") matches every location in that region.
        return $query->where(fn ($q) => $q
            ->where('location', $location)
            ->orWhere('region', $location));
    }

    public function scopeInRegion($query, string $region)
    {
        return $query->where('region', $region);
    }

    /** Partial search on the location or its region. */
    public function scopeLocationLike($query, string $term)
    {
        $like = '%'.trim($term).'%';

        return $query->where(fn ($q) => $q->where('location', 'like', $like)->orWhere('region', 'like', $like));
    }

    public function scopeSearch(
        $query,
        string $term
    ) {
        return $query->where(
            function ($query) use ($term) {
                $query
                    ->where(
                        'item_name',
                        'like',
                        "%{$term}%"
                    )
                    ->orWhere(
                        'brand',
                        'like',
                        "%{$term}%"
                    )
                    ->orWhere(
                        'specification',
                        'like',
                        "%{$term}%"
                    )
                    ->orWhere(
                        'category',
                        'like',
                        "%{$term}%"
                    )
                    ->orWhere(
                        'supplier',
                        'like',
                        "%{$term}%"
                    )
                    ->orWhereHas(
                        'hardwareCategory',
                        fn ($category) =>
                            $category->where(
                                'name',
                                'like',
                                "%{$term}%"
                            )
                    );
            }
        );
    }

    public function getPriceTypeLabelAttribute(): string
    {
        return match (
            $this->price_type
        ) {
            self::TYPE_FACTORY =>
                'Factory Price',

            default =>
                'Hardware Price',
        };
    }

    public function getLowestPriceAttribute(): float
    {
        return (float) (
            $this
                ->priceHistories()
                ->min('price')
            ?? $this->price
        );
    }

    public function getHighestPriceAttribute(): float
    {
        return (float) (
            $this
                ->priceHistories()
                ->max('price')
            ?? $this->price
        );
    }

    public function getAveragePriceAttribute(): float
    {
        return (float) (
            $this
                ->priceHistories()
                ->avg('price')
            ?? $this->price
        );
    }
}