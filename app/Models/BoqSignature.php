<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A sign-off on a BOQ: the preparer's (the owner or a team member) or the
 * client's. One of each per BOQ; signing again replaces it. The image is a
 * trimmed, transparent PNG on the public disk.
 */
class BoqSignature extends Model
{
    public const ROLE_PREPARER = 'preparer';

    public const ROLE_CLIENT = 'client';

    public const ROLES = [self::ROLE_PREPARER, self::ROLE_CLIENT];

    public const METHOD_DRAWN = 'drawn';

    public const METHOD_UPLOADED = 'uploaded';

    protected $fillable = [
        'boq_id',
        'role',
        'name',
        'title',
        'signed_at',
        'image_path',
        'method',
        'ip',
        'user_agent',
        'user_id',
        'signature_request_id',
    ];

    protected $hidden = ['ip', 'user_agent'];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'signed_at' => 'date',
        ];
    }

    public function boq(): BelongsTo
    {
        return $this->belongsTo(Boq::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function signatureRequest(): BelongsTo
    {
        return $this->belongsTo(SignatureRequest::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    /** True when the client signed through the emailed / shared link. */
    public function signedRemotely(): bool
    {
        return $this->signature_request_id !== null;
    }
}
