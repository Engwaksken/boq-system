<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Boq extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'organisation_id',
        'owner_id',
        'name',
        'code',
        'reference',
        'description',
        'original_language',
        'currency',
        'status',
        'source_type',
        'source_file_path',
        'version',
        'metadata',
        'company_snapshot',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'company_snapshot' => 'array',
        ];
    }

    /**
     * The owner is set once, on creation, from the signed-in user (or the project
     * owner); it decides whose company identity brands the BOQ and cannot be changed
     * by other users.
     */
    protected static function booted(): void
    {
        static::creating(function (Boq $boq): void {
            $boq->owner_id ??= auth()->id() ?? Project::whereKey($boq->project_id)->value('user_id');
            $boq->reference ??= 'BOQ-'.now()->format('Ymd').'-'.strtoupper(\Illuminate\Support\Str::random(5));
        });

        static::updating(function (Boq $boq): void {
            if ($boq->isDirty('owner_id') && $boq->getOriginal('owner_id') !== null) {
                $boq->owner_id = $boq->getOriginal('owner_id');
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Company details for exports: the saved snapshot, else the owner's current profile.
     *
     * @return array<string, mixed>|null
     */
    /**
     * Where the BOQ is priced: the project's location, district or country, then
     * the user's own location, the market location from Admin > Settings and the
     * site's country. Empty only when none of these is known.
     */
    public function pricingLocation(?User $user = null): string
    {
        $this->loadMissing('project');
        $project = $this->project;

        $candidates = [
            $project?->location,
            $project?->district,
            $project?->country,
            $user?->location,
            \App\Support\Regional::marketLocation(),
            \App\Support\Regional::countryName(),
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    public function brandingIdentity(): ?array
    {
        if (! empty($this->company_snapshot)) {
            return $this->company_snapshot;
        }

        return $this->owner?->companyProfile?->snapshot();
    }

    /**
     * The project this BOQ belongs to.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The organisation that owns this BOQ.
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * Facilities in this BOQ.
     */
    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }

    /**
     * Items in this BOQ.
     */
    public function items(): HasMany
    {
        return $this->hasMany(BoqItem::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Summaries for this BOQ.
     */
    public function summaries(): HasMany
    {
        return $this->hasMany(BoqSummary::class);
    }

    /**
     * Sign-offs on this BOQ (at most one preparer and one client signature).
     */
    public function signatures(): HasMany
    {
        return $this->hasMany(BoqSignature::class);
    }

    public function preparerSignature(): HasOne
    {
        return $this->hasOne(BoqSignature::class)->where('role', BoqSignature::ROLE_PREPARER);
    }

    public function clientSignature(): HasOne
    {
        return $this->hasOne(BoqSignature::class)->where('role', BoqSignature::ROLE_CLIENT);
    }

    /**
     * Links sent to the client to sign this BOQ remotely.
     */
    public function signatureRequests(): HasMany
    {
        return $this->hasMany(SignatureRequest::class);
    }

    /**
     * Uploaded copies of the physically signed BOQ, newest first.
     */
    public function signedDocuments(): HasMany
    {
        return $this->hasMany(BoqSignedDocument::class)->latest()->latest('id');
    }
}
