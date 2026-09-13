<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HardwareBookmark extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'hardware_price_id',
        'location',
        'notes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hardwarePrice(): BelongsTo
    {
        return $this->belongsTo(HardwarePrice::class);
    }
}