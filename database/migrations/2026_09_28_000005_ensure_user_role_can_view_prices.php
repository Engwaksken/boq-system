<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Get Prices" is part of every customer account: make sure the permission
     * exists and the User role has it (older installs could miss either).
     */
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $roleId = DB::table('roles')->where('slug', 'user')->value('id');
        if (! $roleId) {
            return; // fresh install: the seeder creates the role with this permission
        }

        $now = now();
        $permissionId = DB::table('permissions')->where('slug', 'hardware-prices.view')->value('id');

        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId(array_filter([
                'name' => 'View Hardware Prices',
                'slug' => 'hardware-prices.view',
                'module' => Schema::hasColumn('permissions', 'module') ? 'rates' : null,
                'created_at' => $now,
                'updated_at' => $now,
            ], fn ($value) => $value !== null));
        }

        $linked = DB::table('permission_role')
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->exists();

        if (! $linked) {
            DB::table('permission_role')->insert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Keep the permission: removing it would hide prices from customers.
    }
};
