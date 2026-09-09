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
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique(); // stripe, bank_transfer, mobile_money
            $table->string('driver'); // stripe, bank_transfer, mobile_money
            $table->text('description')->nullable();
            $table->json('config')->nullable(); // encrypted credentials placeholders
            $table->json('supported_currencies')->nullable();
            $table->json('supported_countries')->nullable();
            $table->json('supported_methods')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_test_mode')->default(true);
            $table->string('webhook_url')->nullable();
            $table->unsignedInteger('payment_timeout_seconds')->default(900);
            $table->json('refund_settings')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
