<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Entitlement extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'organisation_id',
        'subscription_id',
        'feature_id',
        'plan_id',
        'topup_purchase_id',
        'source',
        'status',
        'granted_at',
        'expires_at',
        'is_permanent',
        'limits',
        'usage',
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
            'granted_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_permanent' => 'boolean',
            'limits' => 'array',
            'usage' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * The user this entitlement belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The organisation this entitlement belongs to.
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * The subscription that granted this entitlement.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * The feature this entitlement grants.
     */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    /**
     * The plan this entitlement came from.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * The top-up purchase that granted this entitlement.
     */
    public function topupPurchase(): BelongsTo
    {
        return $this->belongsTo(TopupPurchase::class);
    }

    /**
     * Check whether this entitlement is currently valid.
     */
    public function isValid(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->is_permanent) {
            return true;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /**
     * Get the remaining usage for a given limit key, or null if unlimited.
     */
    public function remainingFor(string $key): ?int
    {
        $limits = $this->limits ?? [];
        $usage = $this->usage ?? [];

        if (! isset($limits[$key])) {
            return null;
        }

        return max(0, (int) $limits[$key] - (int) ($usage[$key] ?? 0));
    }
}
