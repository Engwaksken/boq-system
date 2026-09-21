<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'quotation_id',
        'boq_item_id',
        'sort_order',
        'product',
        'description',
        'unit',
        'quantity',
        'unit_price',
        'vat_rate',
        'line_total',
        'matched',
        'matched_rate_id',
        'approved',
        'source_item_code',
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
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'line_total' => 'decimal:2',
            'matched' => 'boolean',
            'approved' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * The quotation this item belongs to.
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * The BOQ item this quotation line matches.
     */
    public function boqItem(): BelongsTo
    {
        return $this->belongsTo(BoqItem::class);
    }

    /**
     * The rate library entry this line matched to.
     */
    public function matchedRate(): BelongsTo
    {
        return $this->belongsTo(Rate::class, 'matched_rate_id');
    }

    /**
     * Recompute and persist the line total.
     */
    public function calculateLineTotal(): float
    {
        $total = (float) $this->quantity * (float) $this->unit_price;

        return round($total, 2);
    }
}