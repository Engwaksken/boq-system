<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoqItemPriceSuggestion extends Model
{
    protected $fillable = ['boq_item_id', 'location', 'suggested_rate', 'confidence', 'explanation', 'provider', 'currency'];

    public function boqItem(): BelongsTo
    {
        return $this->belongsTo(BoqItem::class);
    }
}
