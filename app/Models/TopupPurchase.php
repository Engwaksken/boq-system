<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopupPurchase extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'topup_id',
        'user_id',
        'organisation_id',
        'subscription_id',
        'transaction_id',
        'status',
        'purchased_at',
        'activated_at',
        'expires_at',
        'is_permanent',
        'version',
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
            'purchased_at' => 'datetime',
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_permanent' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * The top-up that was purchased.
     */
    public function topup(): BelongsTo
    {
        return $this->belongsTo(Topup::class);
    }

    /**
     * The user who purchased the top-up.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The organisation the top-up benefits.
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * The subscription the top-up was added to.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * The payment transaction backing this purchase.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Whether the purchase is currently usable.
     */
    public function isValid(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->is_permanent || $this->expires_at === null) {
            return true;
        }

        return $this->expires_at->isFuture();
    }
}