<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('boq_id')->nullable()->constrained('boqs')->nullOnDelete();
            $table->foreignId('boq_item_id')->nullable()->constrained('boq_items')->nullOnDelete();
            $table->text('description');
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 50);
            $table->decimal('rate', 14, 2);
            $table->decimal('total', 14, 2);
            $table->timestamps();
            $table->index(['expense_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_items');
    }
};
