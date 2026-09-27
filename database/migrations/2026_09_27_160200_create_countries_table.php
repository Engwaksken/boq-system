<?php

use Database\Seeders\CountriesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->char('iso2', 2)->unique();
            $table->string('name', 100);
            $table->string('dial_code', 8)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'name']);
        });

        (new CountriesSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
