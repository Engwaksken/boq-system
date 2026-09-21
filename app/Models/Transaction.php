<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'idempotency_key',
        'user_id',
        'organisation_id',
        'plan_id',
        'subscription_id',
        'payment_gateway_id',
        'product_type',
        'product_id',
        'amount',
        'currency',
        'payment_method',
        'gateway_transaction_id',
        'status',
        'initiated_at',
        'completed_at',
        'failed_at',
        'refund_status',
        'failure_reason',
        'metadata',
        'payer_id',
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
            'initiated_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'metadata' => 'array',
            'payer_id' => 'integer',
        ];
    }

    /**
     * The beneficiary user for this transaction (backward compatibility: user_id).
     * For self-paid transactions, this is the same as the payer.
     * For proxy-paid transactions, this is the user receiving the product/service.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The user who paid for this transaction (payer).
     * Null for self-paid transactions where the beneficiary pays for themselves.
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    /**
     * The organisation for this transaction.
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * The plan purchased in this transaction.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * The subscription related to this transaction.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * The payment gateway used.
     */
    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class);
    }

    /**
     * The invoice generated for this transaction.
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * The top-up purchased in this transaction.
     */
    public function topup(): BelongsTo
    {
        return $this->belongsTo(Topup::class, 'product_id');
    }

    /**
     * The purchase record linked to this transaction.
     */
    public function topupPurchase(): HasOne
    {
        return $this->hasOne(TopupPurchase::class);
    }

    /**
     * Check whether this transaction was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'successful';
    }

    /**
     * Scope to filter transactions paid by a specific payer.
     */
    public function scopeForPayer($query, int $userId)
    {
        return $query->where('payer_id', $userId);
    }
}
