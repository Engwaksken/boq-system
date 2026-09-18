<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'organisation_id',
        'plan_id',
        'previous_plan_id',
        'status',
        'access_type',
        'payment_status',
        'start_date',
        'end_date',
        'renewal_date',
        'grace_period_end_date',
        'cancellation_date',
        'auto_renewal',
        'product_version',
        'metadata',
        'payer_id',
        'beneficiary_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'renewal_date' => 'datetime',
            'grace_period_end_date' => 'datetime',
            'cancellation_date' => 'datetime',
            'auto_renewal' => 'boolean',
            'metadata' => 'array',
            'payer_id' => 'integer',
            'beneficiary_id' => 'integer',
        ];
    }

    /**
     * The beneficiary user for this subscription (backward compatibility: user_id).
     * For self-subscriptions, this is the same as the payer.
     * For proxy subscriptions, this is the user receiving the subscription benefits.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The user who paid for this subscription (payer).
     * Null for self-subscriptions where the beneficiary pays for themselves.
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    /**
     * The user who benefits from this subscription.
     * For self-subscriptions, this equals user_id.
     * For proxy subscriptions, this is the target user receiving the subscription.
     */
    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beneficiary_id');
    }

    /**
     * The organisation that owns this subscription.
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * The plan this subscription is on.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * The previous plan (for upgrade/downgrade tracking).
     */
    public function previousPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'previous_plan_id');
    }

    /**
     * Entitlements granted by this subscription.
     */
    public function entitlements(): HasMany
    {
        return $this->hasMany(Entitlement::class);
    }

    /**
     * Transactions for this subscription.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Invoices for this subscription.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Check whether this subscription is currently active.
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trial', 'grace_period']);
    }

    /**
     * Check whether this subscription has expired.
     */
    public function isExpired(): bool
    {
        return $this->end_date !== null && $this->end_date->isPast() && ! $this->isActive();
    }

    /**
     * Scope to filter subscriptions for a specific beneficiary.
     */
    public function scopeForBeneficiary($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('beneficiary_id', $userId)
              ->orWhere(function ($q2) use ($userId) {
                  $q2->whereNull('beneficiary_id')->where('user_id', $userId);
              });
        });
    }

    /**
     * Scope to filter subscriptions paid by a specific payer.
     */
    public function scopeForPayer($query, int $userId)
    {
        return $query->where('payer_id', $userId);
    }

    /**
     * Scope to filter proxy subscriptions (where payer !== beneficiary).
     */
    public function scopeProxySubscriptions($query)
    {
        return $query->whereNotNull('payer_id')
            ->whereRaw('payer_id != COALESCE(beneficiary_id, user_id)');
    }

    /**
     * Check if this is a proxy subscription (paid by someone else for a beneficiary).
     */
    public function isProxy(): bool
    {
        $beneficiaryId = $this->beneficiary_id ?? $this->user_id;
        return $this->payer_id !== null && $this->payer_id !== $beneficiaryId;
    }

    /**
     * Get the beneficiary user (falls back to user relationship for backward compatibility).
     */
    public function getBeneficiary(): ?User
    {
        return $this->beneficiary ?? $this->user;
    }
}
