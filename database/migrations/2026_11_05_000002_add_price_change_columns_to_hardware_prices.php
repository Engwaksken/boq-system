<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The last price change of each hardware price (previous price, change and %).
 * The price page, Compare page, API and AI tools already read price_change and
 * price_change_percent, but the columns never existed, so they were always empty
 * (and the API statistics query failed). HardwarePrice keeps them current on save.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hardware_prices', function (Blueprint $table) {
            $table->decimal('previous_price', 15, 2)->nullable()->after('price');
            $table->decimal('price_change', 15, 2)->nullable()->after('previous_price');
            $table->decimal('price_change_percent', 9, 2)->nullable()->after('price_change');
        });

        // Backfill from history: the most recent recorded price that differs from today's.
        DB::table('hardware_prices')->select(['id', 'price'])->orderBy('id')->chunkById(500, function ($prices): void {
            foreach ($prices as $price) {
                $previous = DB::table('price_histories')
                    ->where('hardware_price_id', $price->id)
                    ->whereRaw('ROUND(price, 2) <> ROUND(?, 2)', [$price->price])
                    ->orderByDesc('recorded_at')
                    ->orderByDesc('id')
                    ->value('price');

                if ($previous === null) {
                    continue;
                }

                $change = round((float) $price->price - (float) $previous, 2);
                DB::table('hardware_prices')->where('id', $price->id)->update([
                    'previous_price' => $previous,
                    'price_change' => $change,
                    'price_change_percent' => (float) $previous != 0.0 ? round($change / (float) $previous * 100, 2) : null,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('hardware_prices', function (Blueprint $table) {
            $table->dropColumn(['previous_price', 'price_change', 'price_change_percent']);
        });
    }
};
