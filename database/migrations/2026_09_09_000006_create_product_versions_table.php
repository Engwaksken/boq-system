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
        Schema::create('product_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version_number'); // e.g. 1.0, 1.5, 2.0
            $table->string('name');
            $table->text('release_notes')->nullable();
            $table->date('release_date')->nullable();
            $table->string('classification'); // major, minor, patch
            $table->json('included_features')->nullable();
            $table->boolean('requires_topup')->default(false);
            $table->json('eligible_plans')->nullable();
            $table->string('minimum_supported_version')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_versions');
    }
};
