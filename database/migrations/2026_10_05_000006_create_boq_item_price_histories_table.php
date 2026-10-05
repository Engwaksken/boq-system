<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boq_item_price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boq_id')->constrained('boqs')->cascadeOnDelete();
            $table->foreignId('boq_item_id')->constrained('boq_items')->cascadeOnDelete();
            $table->string('field', 30);
            $table->decimal('old_value', 15, 2)->nullable();
            $table->decimal('new_value', 15, 2)->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('source', 50)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['boq_item_id', 'changed_at']);
            $table->index(['boq_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boq_item_price_histories');
    }
};
