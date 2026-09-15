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
        ];
    }

    /**
     * The user who owns this subscription.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
}
