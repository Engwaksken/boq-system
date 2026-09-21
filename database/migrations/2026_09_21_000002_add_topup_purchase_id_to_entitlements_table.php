<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('entitlements', function (Blueprint $table) {
            $table->foreignId('topup_purchase_id')
                ->nullable()
                ->after('plan_id')
                ->constrained()
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entitlements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('topup_purchase_id');
        });
    }
};