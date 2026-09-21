<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topup extends Model
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
        'type',
        'price',
        'currency',
        'duration_days',
        'is_permanent',
        'release_version',
        'included_features',
        'usage_credits',
        'limits',
        'applicable_plans',
        'purchase_limit',
        'requires_confirmation',
        'is_active',
        'is_archived',
        'display_order',
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
            'price' => 'decimal:2',
            'is_permanent' => 'boolean',
            'included_features' => 'array',
            'usage_credits' => 'array',
            'limits' => 'array',
            'applicable_plans' => 'array',
            'requires_confirmation' => 'boolean',
            'is_active' => 'boolean',
            'is_archived' => 'boolean',
        ];
    }

    /**
     * The purchases for this top-up.
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(TopupPurchase::class);
    }

    /**
     * Whether the top-up is available to a given plan code (or all plans).
     */
    public function allowsPlan(?string $planCode): bool
    {
        $plans = $this->applicable_plans;

        if (empty($plans) || $planCode === null) {
            return true;
        }

        return in_array($planCode, $plans, true);
    }

    /**
     * Whether the top-up is purchasable given an optional current plan code.
     */
    public function purchasable(?string $planCode = null): bool
    {
        if (! $this->is_active || $this->is_archived) {
            return false;
        }

        return $this->allowsPlan($planCode);
    }

    /**
     * Whether the top-up entitles a product/feature update.
     */
    public function isFeatureUpdate(): bool
    {
        return in_array($this->type, ['feature_unlock', 'version_update', 'bundle'], true)
            || $this->release_version !== null;
    }

    /**
     * Whether the top-up grants usage credits.
     */
    public function isUsageTopup(): bool
    {
        return ! empty($this->usage_credits ?? []);
    }

    /**
     * Map usage credit keys to the feature code that grants them.
     */
    public static function creditFeatureCode(string $key): string
    {
        return match ($key) {
            'ai_credits' => 'credits.ai',
            'ocr_pages' => 'credits.ocr',
            'translations' => 'credits.translation',
            'storage_bytes' => 'credits.storage',
            'user_seats' => 'credits.seats',
            'projects' => 'credits.projects',
            'boqs' => 'credits.boqs',
            'report_exports' => 'credits.exports',
            default => 'credits.'.str_replace('_', '.', $key),
        };
    }
}