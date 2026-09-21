<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'invoice_number',
        'user_id',
        'organisation_id',
        'transaction_id',
        'subscription_id',
        'billing_address',
        'product_type',
        'product_id',
        'amount',
        'tax_amount',
        'total_amount',
        'currency',
        'status',
        'payment_date',
        'due_date',
        'locale',
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
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'payment_date' => 'datetime',
            'due_date' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * The user this invoice belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The organisation this invoice belongs to.
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * The transaction this invoice relates to.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * The subscription this invoice relates to.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * The top-up this invoice relates to.
     */
    public function topup(): BelongsTo
    {
        return $this->belongsTo(Topup::class, 'product_id');
    }
}
