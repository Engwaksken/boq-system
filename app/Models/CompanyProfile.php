<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CompanyProfile extends Model
{
    protected $fillable = [
        'user_id',
        'company_name',
        'logo_path',
        'registration_number',
        'tin',
        'country',
        'city',
        'physical_address',
        'postal_address',
        'telephone',
        'alt_telephone',
        'email',
        'website',
        'description',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    /**
     * Frozen copy stored on a BOQ, so exported documents keep the identity used
     * when they were first generated.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return $this->only([
            'company_name', 'registration_number', 'tin', 'country', 'city', 'physical_address',
            'postal_address', 'telephone', 'alt_telephone', 'email', 'website',
        ]) + [
            'logo_path' => $this->logo_path,
            'country_name' => $this->country ? Country::where('iso2', $this->country)->value('name') : null,
            'captured_at' => now()->toIso8601String(),
        ];
    }
}
