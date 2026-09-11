<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoqPricingBatch extends Model
{
    protected $fillable = ['boq_id', 'user_id', 'location', 'status', 'total_items', 'processed_items', 'failed_items'];

    public function boq(): BelongsTo
    {
        return $this->belongsTo(Boq::class);
    }
}
