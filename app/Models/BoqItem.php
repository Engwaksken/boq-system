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
     * The rate fields whose changes are audited into the price history.
     *
     * @var list<string>
     */
    public const PRICE_FIELDS = ['original_rate', 'ai_suggested_rate', 'reviewed_rate', 'approved_rate'];

    /**
     * Keep approved BOQ values in sync whenever the model is persisted.
     */
    protected static function booted(): void
    {
        static::saving(function (self $boqItem): void {
            $boqItem->recalculateAmount();
        });

        // Keep the price for each location, so a BOQ can be priced for several.
        static::saved(function (self $boqItem): void {
            if ($boqItem->ai_suggested_rate !== null
                && ($boqItem->wasRecentlyCreated || $boqItem->wasChanged(['ai_suggested_rate', 'location', 'pricing_source']))) {
                BoqLocationPrice::record($boqItem);
            }
        });

        // Record an immutable history entry whenever an item's price changes.
        static::updated(function (self $boqItem): void {
            $boqItem->recordPriceHistory();
        });
    }

    /**
     * Append a price-history entry for each rate field that changed on this update.
     */
    public function recordPriceHistory(): void
    {
        foreach (self::PRICE_FIELDS as $field) {
            if (! $this->wasChanged($field)) {
                continue;
            }

            $old = $this->getOriginal($field);
            $new = $this->{$field};

            // Skip null-to-null and numerically identical transitions.
            if (($old === null && $new === null) || ($old !== null && $new !== null
                && bccomp($this->normaliseRate($old), $this->normaliseRate($new), 2) === 0)) {
                continue;
            }

            BoqItemPriceHistory::create([
                'boq_id' => $this->boq_id,
                'boq_item_id' => $this->id,
                'field' => $field,
                'old_value' => $old,
                'new_value' => $new,
                'currency' => $this->currency,
                'source' => $this->pricing_source,
                'user_id' => auth()->id(),
                'changed_at' => now(),
            ]);
        }
    }

    private function normaliseRate(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
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
        'pricing_status',
        'priced_at',
        'pricing_error',
        'batch_number',
        'pricing_job_id',
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
            'pricing_status' => 'string',
            'priced_at' => 'datetime',
            'batch_number' => 'integer',
        ];
    }

    /**
     * The BOQ this item belongs to.
     */
    public function boq(): BelongsTo
    {
        return $this->belongsTo(Boq::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function priceHistory(): HasMany
    {
        return $this->hasMany(BoqItemPriceHistory::class)->latest('changed_at');
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
     * The pricing job this item belongs to.
     */
    public function pricingJob(): BelongsTo
    {
        return $this->belongsTo(BoqPricingJob::class, 'pricing_job_id');
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

    /**
     * Scope: Items that have not been priced yet.
     */
    public function scopeUnpriced($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('pricing_status')
              ->orWhere('pricing_status', 'pending');
        });
    }

    /**
     * Scope: Items that have been successfully priced.
     */
    public function scopePriced($query)
    {
        return $query->where('pricing_status', 'priced');
    }

    /**
     * Scope: Items that failed pricing.
     */
    public function scopeFailed($query)
    {
        return $query->where('pricing_status', 'failed');
    }

    /**
     * Scope: Items for a specific pricing job.
     */
    public function scopeForPricingJob($query, int $jobId)
    {
        return $query->where('pricing_job_id', $jobId);
    }

    /**
     * Mark item as being processed for pricing.
     */
    public function markAsPricing(int $jobId, int $batchNumber): void
    {
        $this->update([
            'pricing_job_id' => $jobId,
            'batch_number' => $batchNumber,
            'pricing_status' => 'processing',
            'pricing_error' => null,
        ]);
    }

    /**
     * Mark item as successfully priced.
     */
    public function markAsPriced(): void
    {
        $this->update([
            'pricing_status' => 'priced',
            'priced_at' => now(),
            'pricing_error' => null,
        ]);
    }

    /**
     * Mark item as failed pricing.
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'pricing_status' => 'failed',
            'pricing_error' => $error,
        ]);
    }

    /**
     * Mark item as skipped (not priced).
     */
    public function markAsSkipped(): void
    {
        $this->update([
            'pricing_status' => 'skipped',
            'pricing_error' => null,
        ]);
    }
}
