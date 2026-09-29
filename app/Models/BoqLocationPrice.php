<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The latest price of a BOQ item for one location.
 */
class BoqLocationPrice extends Model
{
    protected $fillable = [
        'boq_id', 'boq_item_id', 'location', 'location_key', 'rate', 'currency',
        'source', 'hardware_price_id', 'confidence', 'priced_at',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'confidence' => 'decimal:2',
            'priced_at' => 'datetime',
        ];
    }

    public static function keyFor(string $location): string
    {
        return mb_substr(mb_strtolower(trim(preg_replace('/\s+/u', ' ', $location) ?? $location)), 0, 191);
    }

    /** Records (or updates) the price an item was just given for its location. */
    public static function record(BoqItem $item): void
    {
        $location = trim((string) $item->location);
        if ($location === '' || $item->ai_suggested_rate === null) {
            return;
        }

        static::updateOrCreate(
            ['boq_item_id' => $item->id, 'location_key' => static::keyFor($location)],
            [
                'boq_id' => $item->boq_id,
                'location' => $location,
                'rate' => $item->ai_suggested_rate,
                'currency' => $item->currency,
                'source' => $item->pricing_source,
                'hardware_price_id' => $item->hardware_price_id,
                'confidence' => $item->ai_confidence,
                'priced_at' => now(),
            ],
        );
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(BoqItem::class, 'boq_item_id');
    }
}
