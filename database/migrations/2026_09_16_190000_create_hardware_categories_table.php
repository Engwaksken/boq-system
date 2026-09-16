<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hardware_categories')) {
            Schema::create('hardware_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->text('description')->nullable();
                $table->json('default_items')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(100)->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });

            $now = now();

            DB::table('hardware_categories')->insert([
                ['name' => 'Cement', 'description' => 'Cement and cement products', 'default_items' => json_encode(['Portland Cement', 'Waterproof Cement', 'White Cement']), 'is_active' => true, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Aggregates', 'description' => 'Sand, gravel and aggregates', 'default_items' => json_encode(['Sand', 'Gravel', 'Crushed Stone', 'Murram']), 'is_active' => true, 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Steel', 'description' => 'Reinforcement and structural steel', 'default_items' => json_encode(['TMT Bars', 'Binding Wire', 'Steel Mesh', 'Steel Plates']), 'is_active' => true, 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Bricks & Blocks', 'description' => 'Bricks, blocks and masonry units', 'default_items' => json_encode(['Clay Bricks', 'Concrete Blocks', 'Interlocking Blocks']), 'is_active' => true, 'sort_order' => 40, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Timber', 'description' => 'Timber and boards', 'default_items' => json_encode(['Treated Timber', 'Plywood', 'MDF', 'Blockboard', 'Cypress', 'Pine']), 'is_active' => true, 'sort_order' => 50, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Roofing', 'description' => 'Roofing materials and accessories', 'default_items' => json_encode(['Iron Sheets', 'Roofing Tiles', 'Ridges', 'Valleys', 'Gutters']), 'is_active' => true, 'sort_order' => 60, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Plumbing', 'description' => 'Pipes, fittings and plumbing materials', 'default_items' => json_encode(['PVC Pipes', 'HDPE Pipes', 'Fittings', 'Valves', 'Taps', 'Water Tanks']), 'is_active' => true, 'sort_order' => 70, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Electrical', 'description' => 'Electrical materials and accessories', 'default_items' => json_encode(['Cables', 'Conduits', 'Switches', 'Sockets', 'DB Boxes', 'Bulbs']), 'is_active' => true, 'sort_order' => 80, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Paint', 'description' => 'Paints and coatings', 'default_items' => json_encode(['Emulsion Paint', 'Oil Paint', 'Weather Guard', 'Primer', 'Thinner']), 'is_active' => true, 'sort_order' => 90, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Tiles', 'description' => 'Floor and wall tiles', 'default_items' => json_encode(['Ceramic Tiles', 'Porcelain Tiles', 'Floor Tiles', 'Wall Tiles']), 'is_active' => true, 'sort_order' => 100, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Adhesives', 'description' => 'Construction adhesives and sealants', 'default_items' => json_encode(['Tile Adhesive', 'Grout', 'Silicone', 'Construction Adhesive']), 'is_active' => true, 'sort_order' => 110, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Tools', 'description' => 'Construction tools and equipment', 'default_items' => json_encode(['Cement Mixers', 'Vibrators', 'Trowels', 'Levels', 'Measuring Tools']), 'is_active' => true, 'sort_order' => 120, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Safety', 'description' => 'Personal protective equipment', 'default_items' => json_encode(['Helmets', 'Boots', 'Gloves', 'Reflective Vests', 'Safety Nets']), 'is_active' => true, 'sort_order' => 130, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Hardware', 'description' => 'General construction hardware', 'default_items' => json_encode(['Nails', 'Screws', 'Bolts', 'Hinges', 'Locks']), 'is_active' => true, 'sort_order' => 140, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        if (
            Schema::hasTable('hardware_prices')
            && ! Schema::hasColumn('hardware_prices', 'hardware_category_id')
        ) {
            Schema::table('hardware_prices', function (Blueprint $table) {
                $table->foreignId('hardware_category_id')
                    ->nullable()
                    ->after('organisation_id')
                    ->constrained('hardware_categories')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('hardware_prices')) {
            DB::statement(
                'UPDATE hardware_prices hp
                 INNER JOIN hardware_categories hc
                    ON LOWER(TRIM(hp.category)) = LOWER(TRIM(hc.name))
                 SET hp.hardware_category_id = hc.id
                 WHERE hp.hardware_category_id IS NULL'
            );
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('hardware_prices')
            && Schema::hasColumn('hardware_prices', 'hardware_category_id')
        ) {
            Schema::table('hardware_prices', function (Blueprint $table) {
                $table->dropConstrainedForeignId('hardware_category_id');
            });
        }

        Schema::dropIfExists('hardware_categories');
    }
};
