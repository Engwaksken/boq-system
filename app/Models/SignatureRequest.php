<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-time link that lets a client sign a BOQ without an account. Only a
 * hash of the token is stored; the signed URL itself is kept encrypted so the
 * owner can copy or share it again until it is used, revoked or expires.
 */
class SignatureRequest extends Model
{
    protected $fillable = [
        'boq_id',
        'user_id',
        'token_hash',
        'url',
        'email',
        'expires_at',
        'used_at',
        'revoked_at',
    ];

    protected $hidden = ['token_hash', 'url'];

    protected function casts(): array
    {
        return [
            'url' => 'encrypted',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function boq(): BelongsTo
    {
        return $this->belongsTo(Boq::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Links that can still be used. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('used_at')->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function isOpen(): bool
    {
        return $this->used_at === null && $this->revoked_at === null && $this->expires_at?->isFuture();
    }
}
