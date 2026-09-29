<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The latest price of each BOQ item for each location it was priced for, so
     * one BOQ can be priced for several locations and the locations compared.
     */
    public function up(): void
    {
        if (! Schema::hasTable('boq_location_prices')) {
            Schema::create('boq_location_prices', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('boq_id')->constrained()->cascadeOnDelete();
                $table->foreignId('boq_item_id')->constrained('boq_items')->cascadeOnDelete();
                $table->string('location');
                $table->string('location_key', 191);
                $table->decimal('rate', 16, 2);
                $table->string('currency', 8)->nullable();
                $table->string('source')->nullable();
                $table->unsignedBigInteger('hardware_price_id')->nullable();
                $table->decimal('confidence', 5, 2)->nullable();
                $table->timestamp('priced_at')->nullable();
                $table->timestamps();

                $table->unique(['boq_item_id', 'location_key']);
                $table->index(['boq_id', 'location_key']);
            });
        }

        // Keep what is already known: each item's current price at its location.
        $now = now();
        DB::table('boq_items')
            ->whereNotNull('ai_suggested_rate')
            ->whereNotNull('location')
            ->where('location', '!=', '')
            ->orderBy('id')
            ->chunk(500, function ($items) use ($now): void {
                $rows = [];
                foreach ($items as $item) {
                    $rows[] = [
                        'boq_id' => $item->boq_id,
                        'boq_item_id' => $item->id,
                        'location' => trim($item->location),
                        'location_key' => mb_substr(mb_strtolower(trim($item->location)), 0, 191),
                        'rate' => $item->ai_suggested_rate,
                        'currency' => $item->currency,
                        'source' => $item->pricing_source,
                        'hardware_price_id' => $item->hardware_price_id,
                        'confidence' => null,
                        'priced_at' => $item->pricing_date ?? $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                DB::table('boq_location_prices')->insertOrIgnore($rows);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('boq_location_prices');
    }
};
