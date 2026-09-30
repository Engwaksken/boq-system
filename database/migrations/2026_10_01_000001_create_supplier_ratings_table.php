<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One rating per user per supplier per month: users can re-rate each
        // month, which keeps a history for the weekly/monthly/annual rankings.
        Schema::create('supplier_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organisation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('period', 7); // YYYY-MM
            $table->unsignedTinyInteger('rating');
            $table->unsignedTinyInteger('price_rating')->nullable();
            $table->unsignedTinyInteger('quality_rating')->nullable();
            $table->unsignedTinyInteger('delivery_rating')->nullable();
            $table->unsignedTinyInteger('service_rating')->nullable();
            $table->text('comment')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->timestamp('rated_at');
            $table->timestamps();

            $table->unique(['supplier_id', 'user_id', 'period']);
            $table->index(['rated_at', 'is_hidden']);
            $table->index(['supplier_id', 'rated_at']);
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->unsignedInteger('ratings_count')->default(0)->after('rating');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('ratings_count');
        });
        Schema::dropIfExists('supplier_ratings');
    }
};
