<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organisation_id',
        'user_id',
        'name',
        'code',
        'client',
        'contractor',
        'consultant',
        'quantity_surveyor',
        'project_manager',
        'site_engineer',
        'funding_organisation',
        'country',
        'district',
        'location',
        'project_type',
        'start_date',
        'expected_completion_date',
        'contract_value',
        'currency',
        'description',
        'original_language',
        'report_language',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'expected_completion_date' => 'date',
            'contract_value' => 'decimal:2',
        ];
    }

    /**
     * The organisation that owns this project.
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * The user who created this project.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * BOQs belonging to this project.
     */
    public function boqs(): HasMany
    {
        return $this->hasMany(Boq::class);
    }
}
