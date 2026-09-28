<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The self-registered customer role is now "User" (slug `user`), replacing "Viewer".
     * The existing role row is renamed in place so every assignment is kept. If a
     * `user` role already exists, viewer members and permissions are merged into it.
     * Customers can also browse hardware and factory prices.
     */
    private const CUSTOMER_PERMISSIONS = [
        'dashboard.view', 'reports.view',
        'projects.view', 'projects.create', 'projects.edit',
        'boq.view', 'boq.edit',
        'subscriptions.view',
        'hardware-prices.view',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $now = now();
        $viewer = DB::table('roles')->where('slug', 'viewer')->first();
        $user = DB::table('roles')->where('slug', 'user')->first();

        if ($viewer && ! $user) {
            DB::table('roles')->where('id', $viewer->id)->update([
                'slug' => 'user',
                'name' => 'User',
                'description' => 'Standard customer account: own projects and BOQs, price lists and subscription.',
                'updated_at' => $now,
            ]);
            $roleId = $viewer->id;
        } elseif ($viewer && $user) {
            $roleId = $user->id;

            foreach (DB::table('role_user')->where('role_id', $viewer->id)->get() as $link) {
                $exists = DB::table('role_user')->where('role_id', $roleId)->where('user_id', $link->user_id)->exists();
                $exists
                    ? DB::table('role_user')->where('id', $link->id)->delete()
                    : DB::table('role_user')->where('id', $link->id)->update(['role_id' => $roleId]);
            }

            DB::table('permission_role')->where('role_id', $viewer->id)->delete();
            DB::table('roles')->where('id', $viewer->id)->delete();
        } elseif ($user) {
            $roleId = $user->id;
        } else {
            // Fresh install: the seeder creates the User role.
            return;
        }

        foreach (self::CUSTOMER_PERMISSIONS as $slug) {
            $permissionId = DB::table('permissions')->where('slug', $slug)->value('id');

            if ($permissionId && ! DB::table('permission_role')->where('role_id', $roleId)->where('permission_id', $permissionId)->exists()) {
                DB::table('permission_role')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('roles')->where('slug', 'user')->update(['slug' => 'viewer', 'name' => 'Viewer']);
    }
};
