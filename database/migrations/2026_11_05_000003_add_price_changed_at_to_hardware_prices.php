<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When each hardware price last changed, for the Price Changes page
 * ("what changed in the last 7/30/90 days"). Kept current by HardwarePrice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hardware_prices', function (Blueprint $table) {
            $table->timestamp('price_changed_at')->nullable()->after('price_change_percent');
            $table->index('price_changed_at');
        });

        // Existing changes: the price's fetch time is the best record of when it changed.
        DB::table('hardware_prices')
            ->whereNotNull('price_change')
            ->update(['price_changed_at' => DB::raw('COALESCE(fetched_at, updated_at)')]);
    }

    public function down(): void
    {
        Schema::table('hardware_prices', function (Blueprint $table) {
            $table->dropIndex(['price_changed_at']);
            $table->dropColumn('price_changed_at');
        });
    }
};
