<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoqSummary extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'boq_id',
        'facility_id',
        'bill_id',
        'summary_type',
        'name',
        'subtotal',
        'vat',
        'contingency',
        'grand_total',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'vat' => 'decimal:2',
            'contingency' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    /**
     * The BOQ this summary belongs to.
     */
    public function boq(): BelongsTo
    {
        return $this->belongsTo(Boq::class);
    }

    /**
     * The facility this summary belongs to (for facility summaries).
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * The bill this summary belongs to (for bill summaries).
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }
}
