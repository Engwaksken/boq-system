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
            'approved_rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'ai_confidence' => 'decimal:2',
            'translation_confidence' => 'decimal:2',
            'pricing_date' => 'date',
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
        $rate = $this->approved_rate ?? $this->original_rate ?? 0;
        $this->amount = round($this->quantity * $rate, 2);
    }
}
