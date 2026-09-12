<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hardware_price_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 15, 2);
            $table->string('currency', 3)->default('UGX');
            $table->string('supplier');
            $table->string('location')->nullable();
            $table->string('source_url')->nullable();
            $table->timestamp('recorded_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organisation_id', 'hardware_price_id']);
            $table->index(['hardware_price_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_histories');
    }
};