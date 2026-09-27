<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Self-registered users get the "viewer" role. They must be able to create and
     * edit their own projects and BOQs (tenancy is enforced by policies; paid
     * features by entitlements). Older databases were seeded before these were
     * added, which left new sign-ups unable to create a project.
     *
     * Additive and idempotent: only missing links are inserted.
     */
    private const PERMISSIONS = ['projects.view', 'projects.create', 'projects.edit', 'boq.view', 'boq.edit', 'subscriptions.view'];

    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $viewerId = DB::table('roles')->where('slug', 'viewer')->value('id');

        if (! $viewerId) {
            return;
        }

        $now = now();

        foreach (self::PERMISSIONS as $slug) {
            $permissionId = DB::table('permissions')->where('slug', $slug)->value('id');

            if (! $permissionId) {
                continue;
            }

            $linked = DB::table('permission_role')
                ->where('role_id', $viewerId)
                ->where('permission_id', $permissionId)
                ->exists();

            if (! $linked) {
                DB::table('permission_role')->insert([
                    'role_id' => $viewerId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Users who registered on the website before roles were assigned have none.
        DB::table('users')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('role_user')->whereColumn('role_user.user_id', 'users.id'))
            ->orderBy('id')
            ->select(['id', 'organisation_id'])
            ->each(function ($user) use ($viewerId, $now) {
                DB::table('role_user')->insert([
                    'role_id' => $viewerId,
                    'user_id' => $user->id,
                    'organisation_id' => $user->organisation_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        // Permissions granted here may be relied on; nothing is removed.
    }
};
