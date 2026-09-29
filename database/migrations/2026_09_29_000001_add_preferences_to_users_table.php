<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user interface preferences (theme mode, accent colour, density,
 * sidebar default and font size). See User::INTERFACE_PREFERENCES.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'preferences')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->json('preferences')->nullable()->after('display_preferences');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'preferences')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('preferences');
            });
        }
    }
};
