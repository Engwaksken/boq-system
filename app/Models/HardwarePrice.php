<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HardwarePrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'organisation_id',
        'item_name',
        'brand',
        'category',
        'specification',
        'unit',
        'price',
        'currency',
        'supplier',
        'location',
        'source_url',
        'source_reference',
        'fetched_at',
        'is_active',
        'ai_metadata',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'fetched_at' => 'datetime',
        'is_active' => 'boolean',
        'ai_metadata' => 'array',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(PriceHistory::class);
    }

    public function latestHistory(): HasMany
    {
        return $this->hasMany(PriceHistory::class)->latest('recorded_at');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeBySupplier($query, string $supplier)
    {
        return $query->where('supplier', $supplier);
    }

    public function scopeByLocation($query, string $location)
    {
        return $query->where('location', $location);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('item_name', 'like', "%{$term}%")
              ->orWhere('brand', 'like', "%{$term}%")
              ->orWhere('specification', 'like', "%{$term}%")
              ->orWhere('supplier', 'like', "%{$term}%");
        });
    }

    public function getPriceChangeAttribute(): ?float
    {
        $histories = $this->priceHistories()->orderBy('recorded_at')->get();
        if ($histories->count() < 2) {
            return null;
        }
        $first = $histories->first()->price;
        $last = $histories->last()->price;
        return $last - $first;
    }

    public function getPriceChangePercentAttribute(): ?float
    {
        $histories = $this->priceHistories()->orderBy('recorded_at')->get();
        if ($histories->count() < 2) {
            return null;
        }
        $first = $histories->first()->price;
        $last = $histories->last()->price;
        if ($first == 0) {
            return null;
        }
        return round((($last - $first) / $first) * 100, 2);
    }

    public function getLowestPriceAttribute(): float
    {
        return $this->priceHistories()->min('price') ?? $this->price;
    }

    public function getHighestPriceAttribute(): float
    {
        return $this->priceHistories()->max('price') ?? $this->price;
    }

    public function getAveragePriceAttribute(): float
    {
        return $this->priceHistories()->avg('price') ?? $this->price;
    }
};