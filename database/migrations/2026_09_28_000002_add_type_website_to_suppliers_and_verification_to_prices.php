<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (! Schema::hasColumn('suppliers', 'type')) {
                // "supplier" (hardware shop/distributor) or "factory" (manufacturer).
                $table->string('type', 20)->default('supplier')->after('name')->index();
            }
            if (! Schema::hasColumn('suppliers', 'website_url')) {
                $table->string('website_url', 500)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('suppliers', 'address')) {
                $table->string('address', 500)->nullable()->after('location');
            }
        });

        Schema::table('hardware_prices', function (Blueprint $table) {
            if (! Schema::hasColumn('hardware_prices', 'last_verified_at')) {
                $table->timestamp('last_verified_at')->nullable()->after('fetched_at')->index();
            }
            if (! Schema::hasColumn('hardware_prices', 'supplier_id')) {
                $table->foreignId('supplier_id')->nullable()->after('supplier')->constrained('suppliers')->nullOnDelete();
            }
        });

        Schema::table('price_histories', function (Blueprint $table) {
            if (! Schema::hasColumn('price_histories', 'source_reference')) {
                $table->string('source_reference', 500)->nullable()->after('source_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('price_histories', fn (Blueprint $table) => $table->dropColumn('source_reference'));
        Schema::table('hardware_prices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn('last_verified_at');
        });
        Schema::table('suppliers', fn (Blueprint $table) => $table->dropColumn(['type', 'website_url', 'address']));
    }
};
