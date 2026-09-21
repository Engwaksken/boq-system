<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVersion extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'version_number',
        'name',
        'release_notes',
        'release_date',
        'classification',
        'included_features',
        'requires_topup',
        'eligible_plans',
        'minimum_supported_version',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'included_features' => 'array',
            'requires_topup' => 'boolean',
            'eligible_plans' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Subscriptions currently pinned to this product version.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'product_version', 'version_number');
    }
}
