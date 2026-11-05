<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One recorded change to a plan's price (see Plan::booted). */
class PlanPriceChange extends Model
{
    protected $fillable = [
        'plan_id',
        'old_price',
        'new_price',
        'old_currency',
        'new_currency',
        'changed_by',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'old_price' => 'decimal:2',
            'new_price' => 'decimal:2',
            'changed_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class)->withTrashed();
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /** Difference in price; null when the currency changed (amounts are not comparable). */
    public function difference(): ?float
    {
        if ($this->old_price === null || $this->old_currency !== $this->new_currency) {
            return null;
        }

        return (float) $this->new_price - (float) $this->old_price;
    }

    /** Percentage change; null when there is no comparable old price. */
    public function percentChange(): ?float
    {
        $difference = $this->difference();

        return $difference === null || (float) $this->old_price == 0.0
            ? null
            : $difference / (float) $this->old_price * 100;
    }
}
