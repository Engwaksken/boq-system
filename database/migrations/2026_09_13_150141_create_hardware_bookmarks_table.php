<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hardware_bookmarks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('hardware_price_id')
                ->constrained('hardware_prices')
                ->cascadeOnDelete();

            $table->string('location', 150);
            $table->string('notes', 500)->nullable();

            $table->timestamps();

            $table->unique(
                ['user_id', 'hardware_price_id', 'location'],
                'hardware_bookmarks_user_price_location_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hardware_bookmarks');
    }
};