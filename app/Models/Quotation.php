<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'quote_number',
        'supplier_id',
        'project_id',
        'boq_id',
        'status',
        'quotation_date',
        'valid_until',
        'currency',
        'exchange_rate',
        'exchange_rate_source',
        'tax_rate',
        'discount_amount',
        'subtotal',
        'tax_amount',
        'total_amount',
        'source',
        'source_file_name',
        'source_language',
        'notes',
        'metadata',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'accepted_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quotation_date' => 'date',
            'valid_until' => 'date',
            'exchange_rate' => 'decimal:4',
            'tax_rate' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * The supplier the quotation came from.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * The project the quotation relates to.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The BOQ the quotation relates to.
     */
    public function boq(): BelongsTo
    {
        return $this->belongsTo(Boq::class);
    }

    /**
     * The lines on this quotation.
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order');
    }

    /**
     * The user who created this quotation.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The user who reviewed this quotation.
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Whether the quotation is still within its validity period.
     */
    public function isValid(): bool
    {
        return $this->status === 'accepted'
            || ($this->valid_until === null || $this->valid_until->isFuture());
    }
}