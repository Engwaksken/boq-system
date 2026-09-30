<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierRating extends Model
{
    /** Optional detailed ratings, 1-5 each. */
    public const CRITERIA = [
        'price_rating' => 'Price',
        'quality_rating' => 'Quality',
        'delivery_rating' => 'Delivery',
        'service_rating' => 'Service',
    ];

    protected $fillable = [
        'supplier_id', 'user_id', 'organisation_id', 'period', 'rating',
        'price_rating', 'quality_rating', 'delivery_rating', 'service_rating',
        'comment', 'is_hidden', 'rated_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'price_rating' => 'integer',
            'quality_rating' => 'integer',
            'delivery_rating' => 'integer',
            'service_rating' => 'integer',
            'is_hidden' => 'boolean',
            'rated_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Ratings that count (admins can hide abusive or fake ones). */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_hidden', false);
    }
}
