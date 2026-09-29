<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * General market prices: prices without an organisation are shared with every
     * user (customers usually have no organisation, so they saw no prices at all).
     * Prices the platform super admins kept under their own organisation become
     * general prices; other organisations keep their private prices.
     */
    public function up(): void
    {
        foreach (['hardware_prices', 'price_histories'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'organisation_id')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->unsignedBigInteger('organisation_id')->nullable()->change();
                });
            }
        }

        if (! Schema::hasTable('roles') || ! Schema::hasTable('role_user')) {
            return;
        }

        $adminOrganisations = DB::table('users')
            ->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->whereIn('roles.slug', ['super-admin', 'super_admin'])
            ->whereNotNull('users.organisation_id')
            ->distinct()
            ->pluck('users.organisation_id')
            ->all();

        if ($adminOrganisations === []) {
            return;
        }

        DB::table('hardware_prices')->whereIn('organisation_id', $adminOrganisations)->update(['organisation_id' => null]);
        DB::table('price_histories')->whereIn('organisation_id', $adminOrganisations)->update(['organisation_id' => null]);
    }

    public function down(): void
    {
        // General prices cannot be given back to one organisation automatically.
    }
};
