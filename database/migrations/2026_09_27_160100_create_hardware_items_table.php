<?php

use App\Models\HardwareCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Items per category, replacing the JSON default_items list as the source of truth.
     */
    public function up(): void
    {
        Schema::create('hardware_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hardware_category_id')->constrained('hardware_categories')->cascadeOnDelete();
            $table->string('name', 191);
            $table->string('unit', 50)->nullable();
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(100);
            $table->timestamps();

            $table->unique(['hardware_category_id', 'name'], 'hardware_items_category_name_unique');
            $table->index(['hardware_category_id', 'is_active']);
        });

        HardwareCategory::syncAllItemsFromDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('hardware_items');
    }
};
