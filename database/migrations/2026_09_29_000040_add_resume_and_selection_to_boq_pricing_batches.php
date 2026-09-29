<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pricing runs in short chunks so no request or worker hits its time limit:
     * last_item_id is where the next chunk continues. item_ids limits a batch to
     * the items an admin selected ("Get prices" for selected items).
     */
    public function up(): void
    {
        Schema::table('boq_pricing_batches', function (Blueprint $table): void {
            if (! Schema::hasColumn('boq_pricing_batches', 'last_item_id')) {
                $table->unsignedBigInteger('last_item_id')->nullable();
            }
            if (! Schema::hasColumn('boq_pricing_batches', 'item_ids')) {
                $table->json('item_ids')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('boq_pricing_batches', function (Blueprint $table): void {
            foreach (['last_item_id', 'item_ids'] as $column) {
                if (Schema::hasColumn('boq_pricing_batches', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
