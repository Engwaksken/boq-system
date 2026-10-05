<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable record of a BOQ item rate change (original, AI-suggested,
 * reviewed or approved), so past prices can be audited over time.
 */
class BoqItemPriceHistory extends Model
{
    protected $fillable = [
        'boq_id', 'boq_item_id', 'field', 'old_value', 'new_value', 'currency',
        'source', 'user_id', 'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'old_value' => 'decimal:2',
            'new_value' => 'decimal:2',
            'changed_at' => 'datetime',
        ];
    }

    public function boq(): BelongsTo
    {
        return $this->belongsTo(Boq::class);
    }

    public function boqItem(): BelongsTo
    {
        return $this->belongsTo(BoqItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
