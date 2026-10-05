<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'organisation_id', 'email', 'role_id', 'inviter_user_id', 'token_hash',
        'expires_at', 'accepted_at',
        'revoked_at', 'revoked_by_user_id', 'consumed_at', 'attempt_count',
        'last_attempt_at',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempt_count' => 'integer',
            'last_attempt_at' => 'datetime',
        ];
    }

    /**
     * Hash a plaintext invitation code for storage and comparison.
     *
     * The code space is only five digits, so a plain digest would be brute-forceable
     * from a leaked database. Keying the digest with the application secret keeps the
     * codes unrecoverable without both the database and the application key.
     */
    public static function hashCode(string $code): string
    {
        return hash_hmac('sha256', trim($code), (string) config('app.key'));
    }

    /** Store a plaintext invitation code only as its keyed digest. */
    public function setCode(string $code): void
    {
        $this->token_hash = static::hashCode($code);
    }

    public function codeMatches(string $code): bool
    {
        return hash_equals((string) $this->token_hash, static::hashCode($code));
    }

    /** Invitations eligible for acceptance; consumption remains service/controller work. */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')
            ->whereNull('consumed_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_user_id');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }
}
