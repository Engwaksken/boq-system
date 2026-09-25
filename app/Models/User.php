<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organisation_id',
        'name',
        'email',
        'password',
        'locale',
        'timezone',
        'phone',
        'country',
        'original_language',
        'report_language',
        'is_active',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * The organisation this user belongs to.
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * Roles assigned to this user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withPivot('organisation_id')
            ->withTimestamps();
    }

    /**
     * Direct permissions granted to this user.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_user')
            ->withPivot('organisation_id')
            ->withTimestamps();
    }

    /**
     * Subscriptions owned by this user.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Entitlements granted to this user.
     */
    public function entitlements(): HasMany
    {
        return $this->hasMany(Entitlement::class);
    }

    /**
     * Transactions initiated by this user.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Invoices issued to this user.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Projects created by this user.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Audit log entries for this user.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Hardware price bookmarks owned by this user.
     */
    public function hardwareBookmarks(): HasMany
    {
        return $this->hasMany(HardwareBookmark::class);
    }

    /**
     * Check whether the user has a given role by slug.
     */
    public function hasRole(string $role): bool
    {
        return $this->roles()
            ->where('roles.slug', $role)
            ->exists();
    }

    /**
     * Check whether the user has any of the given roles.
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()
            ->whereIn('roles.slug', $roles)
            ->exists();
    }

    /**
     * Check whether this user is a Super Admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasAnyRole(['super-admin', 'super_admin']);
    }

    /**
     * Check whether the user has a given permission by slug.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $hasDirectPermission = $this->permissions()
            ->where('permissions.slug', $permission)
            ->exists();

        if ($hasDirectPermission) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permission) {
                $query->where('permissions.slug', $permission);
            })
            ->exists();
    }

    /**
     * Check whether the user has any of the given permissions.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (empty($permissions)) {
            return true;
        }

        $hasDirectPermission = $this->permissions()
            ->whereIn('permissions.slug', $permissions)
            ->exists();

        if ($hasDirectPermission) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permissions) {
                $query->whereIn('permissions.slug', $permissions);
            })
            ->exists();
    }

    /**
     * Check whether the user has all of the given permissions.
     */
    public function hasAllPermissions(array $permissions): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        foreach ($permissions as $permission) {
            if (! $this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all permission slugs for this user
     * through roles and direct grants.
     *
     * @return array<int, string>
     */
    public function getAllPermissionSlugs(): array
    {
        if ($this->isSuperAdmin()) {
            return Permission::query()
                ->pluck('slug')
                ->all();
        }

        $directPermissions = $this->permissions()
            ->pluck('permissions.slug');

        $rolePermissions = $this->roles()
            ->with('permissions')
            ->get()
            ->flatMap(
                fn (Role $role) => $role->permissions->pluck('slug')
            );

        return $directPermissions
            ->merge($rolePermissions)
            ->unique()
            ->values()
            ->all();
    }
}
