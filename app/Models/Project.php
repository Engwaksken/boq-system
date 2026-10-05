<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
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

    /** Assignments granting users access to this project. */
    public function assignments(): HasMany
    {
        return $this->hasMany(ProjectAssignment::class);
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        if ($user->organisation_id === null) {
            return $query->whereNull('organisation_id')->where('user_id', $user->id);
        }

        $query->where('organisation_id', $user->organisation_id);
        if (! $this->isOrganisationAdministrator($user)) {
            $query->whereHas('assignments', fn (Builder $assignments) => $assignments
                ->where('user_id', $user->id)->whereNull('deleted_at'));
        }

        return $query;
    }

    public static function createForUser(User $user, array $attributes): self
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($user, $attributes) {
            $project = static::create(array_merge($attributes, [
                'user_id' => $user->id, 'organisation_id' => $user->organisation_id,
            ]));
            $project->assignments()->create([
                'user_id' => $user->id, 'role' => 'project-manager', 'assigned_by' => $user->id,
            ]);

            return $project;
        });
    }

    public function isAccessibleTo(User $user): bool
    {
        if ($user->organisation_id === null) {
            return $this->organisation_id === null && $this->user_id === $user->id;
        }

        return $this->organisation_id === $user->organisation_id
            && ($this->isOrganisationAdministrator($user) || $this->assignments()
                ->where('user_id', $user->id)->whereNull('deleted_at')->exists());
    }

    private function isOrganisationAdministrator(User $user): bool
    {
        return $user->roles()->whereIn('roles.slug', ['administrator', 'super-admin', 'super_admin'])
            ->wherePivot('organisation_id', $user->organisation_id)->exists();
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
