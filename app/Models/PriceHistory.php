<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'organisation_id',
        'hardware_price_id',
        'price',
        'currency',
        'supplier',
        'location',
        'source_url',
        'recorded_at',
        'metadata',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'recorded_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function hardwarePrice(): BelongsTo
    {
        return $this->belongsTo(HardwarePrice::class);
    }
};