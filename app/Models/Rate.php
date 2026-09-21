<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rate extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'item',
        'description',
        'name_translations',
        'description_translations',
        'original_language',
        'category',
        'unit',
        'rate',
        'currency',
        'country',
        'region',
        'supplier_id',
        'source_type',
        'source_reference',
        'effective_from',
        'effective_until',
        'review_required_at',
        'verification_status',
        'verified_by',
        'verified_at',
        'is_active',
        'metadata',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name_translations' => 'array',
            'description_translations' => 'array',
            'rate' => 'decimal:2',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'review_required_at' => 'date',
            'verified_at' => 'datetime',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * The supplier this rate is sourced from.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * The user who verified this rate.
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * The user who created this rate.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Quotation items matched to this rate.
     */
    public function quotationItems(): HasMany
    {
        return $this->hasMany(QuotationItem::class, 'matched_rate_id');
    }

    /**
     * Whether the rate is currently effective (approved, active and in date).
     */
    public function isEffective(?string $at = null): bool
    {
        $at ??= now()->toDateString();

        if ($this->verification_status !== 'approved' || ! $this->is_active) {
            return false;
        }

        if ($this->effective_from && $this->effective_from->toDateString() > $at) {
            return false;
        }

        if ($this->effective_until && $this->effective_until->toDateString() < $at) {
            return false;
        }

        return true;
    }

    /**
     * Scope: rates effective at the given date (defaults to today).
     */
    public function scopeEffectiveAt($query, ?string $at = null)
    {
        $at ??= now()->toDateString();

        return $query
            ->where('verification_status', 'approved')
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $at))
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>=', $at));
    }
}