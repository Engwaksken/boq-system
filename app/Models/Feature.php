<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Feature extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'description',
        'name_translations',
        'description_translations',
        'module',
        'version_introduced',
        'is_active',
        'requires_topup',
        'usage_limits',
        'permission_requirements',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name_translations' => 'array',
            'description_translations' => 'array',
            'is_active' => 'boolean',
            'requires_topup' => 'boolean',
            'usage_limits' => 'array',
            'permission_requirements' => 'array',
        ];
    }

    /**
     * Plans that include this feature.
     */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_feature')->withPivot('limits')->withTimestamps();
    }

    /**
     * Entitlements for this feature.
     */
    public function entitlements(): HasMany
    {
        return $this->hasMany(Entitlement::class);
    }
}
