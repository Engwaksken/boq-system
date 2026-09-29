<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoqPricingBatch extends Model
{
    protected $fillable = ['boq_id', 'organisation_id', 'user_id', 'location', 'operation', 'job_batch_id', 'provider', 'current_stage', 'message', 'error_message', 'status', 'total_items', 'processed_items', 'failed_items', 'started_at', 'completed_at', 'cancelled_at', 'last_item_id', 'item_ids'];

    protected $casts = [
        'started_at' => 'datetime',
        'item_ids' => 'array',
        'last_item_id' => 'integer',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function boq(): BelongsTo
    {
        return $this->belongsTo(Boq::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
