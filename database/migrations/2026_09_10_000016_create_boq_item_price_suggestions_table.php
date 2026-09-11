<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boq_item_price_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boq_item_id')->constrained()->cascadeOnDelete();
            $table->string('location');
            $table->decimal('suggested_rate', 15, 2);
            $table->decimal('confidence', 5, 2)->nullable();
            $table->text('explanation')->nullable();
            $table->string('provider')->default('gemini');
            $table->string('currency', 10);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boq_item_price_suggestions');
    }
};
