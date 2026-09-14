<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoqItem extends Model
{
    use HasFactory;

    /**
     * Keep approved BOQ values in sync whenever the model is persisted.
     */
    protected static function booted(): void
    {
        static::saving(function (self $boqItem): void {
            $boqItem->recalculateAmount();
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'boq_id',
        'facility_id',
        'bill_id',
        'element_id',
        'sub_element_id',
        'item_code',
        'description',
        'original_language',
        'unit',
        'quantity',
        'original_rate',
        'ai_suggested_rate',
        'hardware_price_id',
        'match_type',
        'matched_by',
        'matched_at',
        'reviewed_rate',
        'approved_rate',
        'amount',
        'currency',
        'work_category',
        'material_category',
        'location',
        'pricing_source',
        'pricing_date',
        'ai_confidence',
        'translation_confidence',
        'notes',
        'status',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'original_rate' => 'decimal:2',
            'ai_suggested_rate' => 'decimal:2',
            'reviewed_rate' => 'decimal:2',
            'approved_rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'ai_confidence' => 'decimal:2',
            'translation_confidence' => 'decimal:2',
            'pricing_date' => 'date',
            'matched_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    /**
     * The BOQ this item belongs to.
     */
    public function boq(): BelongsTo
    {
        return $this->belongsTo(Boq::class);
    }

    /**
     * The facility this item belongs to.
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * The bill this item belongs to.
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /**
     * The element this item belongs to.
     */
    public function element(): BelongsTo
    {
        return $this->belongsTo(Element::class);
    }

    /**
     * The sub-element this item belongs to.
     */
    public function subElement(): BelongsTo
    {
        return $this->belongsTo(SubElement::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function hardwarePrice(): BelongsTo
    {
        return $this->belongsTo(HardwarePrice::class);
    }

    public function matchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Translations for this item.
     */
    public function translations(): HasMany
    {
        return $this->hasMany(BoqItemTranslation::class);
    }

    /**
     * Recalculate the amount based on quantity and approved rate.
     */
    public function recalculateAmount(): void
    {
        if ($this->approved_rate === null) {
            return;
        }

        $this->amount = round($this->quantity * $this->approved_rate, 2);
    }
}
