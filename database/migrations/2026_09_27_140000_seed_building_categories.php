<?php

use Database\Seeders\BuildingCategoriesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Insert the full list of building categories so forms read them from the database.
     */
    public function up(): void
    {
        if (Schema::hasTable('hardware_categories')) {
            (new BuildingCategoriesSeeder)->run();
        }
    }

    public function down(): void
    {
        // Categories may already be referenced by prices; nothing is removed.
    }
};
