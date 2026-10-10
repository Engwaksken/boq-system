<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Procurement officers upload and manage BOQs, so they must also be able to
     * create them. Older databases seeded the role without "Create BOQs"; grant
     * it here so the "Add BOQ" action is available on the dashboard and BOQs page.
     *
     * Additive and idempotent: only a missing permission link is inserted.
     */
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $roleId = DB::table('roles')->where('slug', 'procurement-officer')->value('id');
        if (! $roleId) {
            return; // fresh install: the seeder creates the role with this permission
        }

        $permissionId = DB::table('permissions')->where('slug', 'boq.create')->value('id');
        if (! $permissionId) {
            return;
        }

        $linked = DB::table('permission_role')
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->exists();

        if ($linked) {
            return;
        }

        DB::table('permission_role')->insert([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Permissions granted here may be relied on; nothing is removed.
    }
};
