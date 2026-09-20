<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Reconcile the hardware_categories table for databases where the
     * canonical schema/seed was skipped because an earlier migration
     * (2026_09_16_091906) had already created the table.
     */
    public function up(): void
    {
        if (! Schema::hasTable('hardware_categories')) {
            Schema::create('hardware_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organisation_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name', 120);
                $table->string('slug', 140)->nullable();
                $table->string('description', 500)->nullable();
                $table->text('default_items')->nullable();
                $table->string('icon', 80)->default('fas fa-boxes-stacked');
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(100)->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        } else {
            if (! Schema::hasColumn('hardware_categories', 'default_items')) {
                Schema::table('hardware_categories', function (Blueprint $table) {
                    $table->text('default_items')->nullable();
                });
            }
            if (! Schema::hasColumn('hardware_categories', 'sort_order')) {
                Schema::table('hardware_categories', function (Blueprint $table) {
                    $table->unsignedInteger('sort_order')->default(100)->index();
                });
            }
            if (! Schema::hasColumn('hardware_categories', 'updated_by')) {
                Schema::table('hardware_categories', function (Blueprint $table) {
                    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                });
            }
        }

        if (DB::table('hardware_categories')->count() === 0) {
            $this->seedDefaultCategories();
        }
    }

    private function seedDefaultCategories(): void
    {
        $now = now();

        $categories = [
            ['Cement', 'Cement and cement products', ['Portland Cement', 'Waterproof Cement', 'White Cement'], 10],
            ['Aggregates', 'Sand, gravel and aggregates', ['Sand', 'Gravel', 'Crushed Stone', 'Murram'], 20],
            ['Steel', 'Reinforcement and structural steel', ['TMT Bars', 'Binding Wire', 'Steel Mesh', 'Steel Plates'], 30],
            ['Bricks & Blocks', 'Bricks, blocks and masonry units', ['Clay Bricks', 'Concrete Blocks', 'Interlocking Blocks'], 40],
            ['Timber', 'Timber and boards', ['Treated Timber', 'Plywood', 'MDF', 'Blockboard', 'Cypress', 'Pine'], 50],
            ['Roofing', 'Roofing materials and accessories', ['Iron Sheets', 'Roofing Tiles', 'Ridges', 'Valleys', 'Gutters'], 60],
            ['Plumbing', 'Pipes, fittings and plumbing materials', ['PVC Pipes', 'HDPE Pipes', 'Fittings', 'Valves', 'Taps', 'Water Tanks'], 70],
            ['Electrical', 'Electrical materials and accessories', ['Cables', 'Conduits', 'Switches', 'Sockets', 'DB Boxes', 'Bulbs'], 80],
            ['Paint', 'Paints and coatings', ['Emulsion Paint', 'Oil Paint', 'Weather Guard', 'Primer', 'Thinner'], 90],
            ['Tiles', 'Floor and wall tiles', ['Ceramic Tiles', 'Porcelain Tiles', 'Floor Tiles', 'Wall Tiles'], 100],
            ['Adhesives', 'Construction adhesives and sealants', ['Tile Adhesive', 'Grout', 'Silicone', 'Construction Adhesive'], 110],
            ['Tools', 'Construction tools and equipment', ['Cement Mixers', 'Vibrators', 'Trowels', 'Levels', 'Measuring Tools'], 120],
            ['Safety', 'Personal protective equipment', ['Helmets', 'Boots', 'Gloves', 'Reflective Vests', 'Safety Nets'], 130],
            ['Hardware', 'General construction hardware', ['Nails', 'Screws', 'Bolts', 'Hinges', 'Locks'], 140],
        ];

        foreach ($categories as [$name, $description, $defaultItems, $sortOrder]) {
            $row = [
                'name' => $name,
                'description' => $description,
                'default_items' => json_encode($defaultItems),
                'is_active' => true,
                'sort_order' => $sortOrder,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('hardware_categories', 'slug')) {
                $row['slug'] = Str::slug($name);
            }

            DB::table('hardware_categories')->insert($row);
        }
    }

    public function down(): void
    {
        // Columns are only added when missing; nothing to remove safely.
    }
};