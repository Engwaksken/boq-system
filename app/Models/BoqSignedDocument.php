<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A scan, photo or PDF of the physically signed BOQ, stored privately.
 * Several versions can be kept; downloads go through an authorised route.
 */
class BoqSignedDocument extends Model
{
    protected $fillable = [
        'boq_id',
        'user_id',
        'disk',
        'path',
        'original_name',
        'size',
        'mime',
    ];

    protected $hidden = ['disk', 'path'];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function boq(): BelongsTo
    {
        return $this->belongsTo(Boq::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPdf(): bool
    {
        return $this->mime === 'application/pdf';
    }
}
