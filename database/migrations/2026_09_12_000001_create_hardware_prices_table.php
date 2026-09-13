<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hardware_prices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organisation_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('item_name', 150);
            $table->string('brand', 100)->nullable();
            $table->string('category', 100);
            $table->string('specification', 191)->nullable();
            $table->string('unit', 50);

            $table->decimal('price', 15, 2);
            $table->string('currency', 3)->default('UGX');

            $table->string('supplier', 150);
            $table->string('location', 150)->nullable();

            $table->string('source_url', 500)->nullable();
            $table->string('source_reference', 191)->nullable();

            $table->timestamp('fetched_at');
            $table->boolean('is_active')->default(true);

            $table->json('ai_metadata')->nullable();

            $table->timestamps();

            $table->index(['organisation_id', 'category']);
            $table->index(['organisation_id', 'item_name']);
            $table->index(['organisation_id', 'supplier']);
            $table->index(['organisation_id', 'location']);
            $table->index(['organisation_id', 'fetched_at']);

            $table->index(['item_name', 'brand']);
            $table->index(['category', 'supplier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hardware_prices');
    }
};