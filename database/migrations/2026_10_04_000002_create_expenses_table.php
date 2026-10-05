<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creator_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('purchaser_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('purchase_date')->index();
            $table->string('supplier')->nullable();
            $table->text('description');
            $table->decimal('quantity', 12, 3);
            $table->string('unit');
            $table->decimal('rate', 14, 2);
            $table->decimal('total', 14, 2);
            $table->char('currency', 3);
            $table->string('payment_method')->nullable();
            $table->boolean('is_planned')->default(true);
            $table->text('explanation')->nullable();
            $table->timestamps();
            $table->index(['organisation_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
