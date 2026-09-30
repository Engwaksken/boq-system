<?php

use App\Models\HardwarePrice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hardware_prices', function (Blueprint $table) {
            $table->string('region', 100)->nullable()->after('location');
            $table->index('region');
        });

        // Existing prices: the region of their supplier, or of a supplier/price
        // at the same location.
        DB::table('hardware_prices')->whereNull('region')->orderBy('id')->chunkById(500, function ($prices) {
            foreach ($prices as $price) {
                $region = HardwarePrice::guessRegion($price->location, $price->supplier_id ?? null);
                if ($region !== null) {
                    DB::table('hardware_prices')->where('id', $price->id)->update(['region' => $region]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('hardware_prices', function (Blueprint $table) {
            $table->dropIndex(['region']);
            $table->dropColumn('region');
        });
    }
};
