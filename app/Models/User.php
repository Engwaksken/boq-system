<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laragear\WebAuthn\Contracts\WebAuthnAuthenticatable;
use Laragear\WebAuthn\WebAuthnAuthentication;

class User extends Authenticatable implements MustVerifyEmail, WebAuthnAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, WebAuthnAuthentication;

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
        'location',
        'original_language',
        'report_language',
        'is_active',
        'last_login_at',
        'avatar_path',
        'preferences',
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['avatar_url'];

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
            'notification_preferences' => 'array',
            'display_preferences' => 'array',
            'preferences' => 'array',
        ];
    }

    /**
     * Notification channels and digest frequency, falling back to defaults.
     *
     * @return array<string, bool|string>
     */
    public function notificationPreferences(): array
    {
        return array_merge(self::DEFAULT_NOTIFICATION_PREFERENCES, $this->notification_preferences ?? []);
    }

    public const DATE_FORMATS = ['Y-m-d' => 'YYYY-MM-DD (ISO)', 'd/m/Y' => 'DD/MM/YYYY', 'm/d/Y' => 'MM/DD/YYYY', 'd.m.Y' => 'DD.MM.YYYY'];

    public const NUMBER_FORMATS = ['1,234.56', '1.234,56', '1 234,56', "1'234.56"];

    /**
     * Display settings (currency, date/number format, rows per page), falling back to system defaults.
     *
     * @return array<string, string|int>
     */
    public function displayPreferences(): array
    {
        return array_merge([
            'currency' => \App\Support\Regional::currency(),
            'date_format' => 'Y-m-d',
            'number_format' => '1,234.56',
            'per_page' => 20,
        ], $this->display_preferences ?? []);
    }

    /**
     * Interface preferences and the values each one accepts. The first value
     * of every list is the default.
     *
     * @var array<string, list<string>>
     */
    public const INTERFACE_PREFERENCES = [
        'theme' => ['light', 'dark', 'system'],
        'accent' => ['green', 'blue', 'indigo', 'purple', 'teal', 'orange', 'rose'],
        'density' => ['comfortable', 'compact'],
        'sidebar' => ['expanded', 'collapsed'],
        'font' => ['default', 'large'],
    ];

    /**
     * One interface preference (theme, accent, density, sidebar, font).
     * A missing or no-longer-allowed stored value falls back to $default,
     * or to the preference's own default when $default is null.
     */
    public function preference(string $key, mixed $default = null): mixed
    {
        $value = ($this->preferences ?? [])[$key] ?? null;
        $allowed = self::INTERFACE_PREFERENCES[$key] ?? null;

        if ($value === null || ($allowed !== null && ! in_array($value, $allowed, true))) {
            return $default ?? ($allowed[0] ?? null);
        }

        return $value;
    }

    /**
     * Every interface preference, with defaults filled in.
     *
     * @return array<string, string>
     */
    public function interfacePreferences(): array
    {
        $preferences = [];

        foreach (array_keys(self::INTERFACE_PREFERENCES) as $key) {
            $preferences[$key] = $this->preference($key);
        }

        return $preferences;
    }

    public const DEFAULT_NOTIFICATION_PREFERENCES = [
        'email_project_updates' => true,
        'email_boq_changes' => true,
        'email_price_alerts' => false,
        'in_app_project_updates' => true,
        'in_app_boq_changes' => true,
        'in_app_approvals' => true,
        'in_app_price_alerts' => true,
        'frequency' => 'immediate',
    ];

    /** Public URL of the profile picture, or null when none was uploaded. */
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->avatar_path)
            : null;
    }

    /** The user's saved default signature (public disk), for reuse on any BOQ. */
    public function signatureUrl(): ?string
    {
        return $this->signature_path
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->signature_path)
            : null;
    }

    public function companyProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CompanyProfile::class);
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

    /** Project assignments held by this user. */
    public function projectAssignments(): HasMany
    {
        return $this->hasMany(ProjectAssignment::class);
    }

    /** Project assignments created by this user. */
    public function assignedProjectAssignments(): HasMany
    {
        return $this->hasMany(ProjectAssignment::class, 'assigned_by');
    }

    public function createdExpenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'creator_user_id');
    }

    public function purchasedExpenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'purchaser_user_id');
    }

    public function sentInvitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'inviter_user_id');
    }

    public function revokedInvitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'revoked_by_user_id');
    }

    public function uploadedExpenseReceipts(): HasMany
    {
        return $this->hasMany(ExpenseReceipt::class, 'uploaded_by_user_id');
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
