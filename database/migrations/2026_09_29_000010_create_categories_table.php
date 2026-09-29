<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Selectable categories that used to be typed in freely: project types and
     * BOQ work sections (trades). Material categories live in hardware_categories.
     */
    public const DEFAULTS = [
        'project' => [
            ['Residential Building', 'Houses, apartments and staff quarters'],
            ['Commercial Building', 'Offices, shops, shopping centres and banks'],
            ['Institutional Building', 'Schools, colleges, dormitories and training centres'],
            ['Health Facility', 'Hospitals, health centres, clinics and laboratories'],
            ['Industrial & Warehouse', 'Factories, workshops, stores and warehouses'],
            ['Hotel & Hospitality', 'Hotels, lodges, restaurants and guest houses'],
            ['Religious Building', 'Churches, mosques and other places of worship'],
            ['Government & Public Building', 'Administration blocks, police stations and courts'],
            ['Mixed-use Development', 'Combined residential and commercial buildings'],
            ['Renovation & Maintenance', 'Refurbishment, repairs and extensions'],
            ['Road Works', 'Roads, parking areas and pavements'],
            ['Bridges & Culverts', 'Bridges, box culverts and drainage crossings'],
            ['Water Supply', 'Water tanks, boreholes, pipelines and treatment works'],
            ['Sanitation & Drainage', 'Toilets, septic tanks, sewers and storm drainage'],
            ['Electrical & Solar Installation', 'Power supply, lighting and solar systems'],
            ['Fencing & External Works', 'Boundary walls, fences, gates and landscaping'],
            ['Agricultural Structures', 'Farm buildings, stores, greenhouses and irrigation'],
            ['Sports & Recreation', 'Sports grounds, courts, pavilions and parks'],
        ],
        'work' => [
            ['Preliminaries & General', 'Site setup, insurances, supervision and general items'],
            ['Demolitions & Site Clearance', 'Demolition, alterations and clearing the site'],
            ['Substructure', 'Excavation, foundations, hardcore and ground slabs'],
            ['Concrete Works', 'In-situ and precast concrete, reinforcement and formwork'],
            ['Masonry & Walling', 'Blockwork, brickwork and stonework'],
            ['Structural Steelwork', 'Steel frames, trusses and connections'],
            ['Roofing', 'Roof structure, coverings, gutters and rainwater goods'],
            ['Carpentry & Joinery', 'Doors, windows, frames, cupboards and timber works'],
            ['Metalwork', 'Burglar proofing, balustrades, gates and steel doors'],
            ['Glazing', 'Glass, glazing and aluminium works'],
            ['Plastering & Screeding', 'Internal and external plaster, render and screeds'],
            ['Floor & Wall Finishes', 'Tiling, terrazzo, skirting and cladding'],
            ['Ceilings & Partitions', 'Suspended ceilings, gypsum and drywall partitions'],
            ['Painting & Decoration', 'Paints, varnishes and decorative finishes'],
            ['Plumbing & Drainage', 'Water supply, sanitary fittings, soil and waste'],
            ['Electrical Installation', 'Wiring, fittings, distribution boards and earthing'],
            ['Mechanical Installation', 'Ventilation, air conditioning and fire protection'],
            ['External Works', 'Paving, drainage, fencing and landscaping'],
            ['Provisional Sums & Contingencies', 'Provisional and prime cost sums, contingencies'],
            ['Dayworks', 'Labour, materials and plant on daywork rates'],
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table): void {
                $table->id();
                $table->string('type', 30)->index();
                $table->string('name');
                $table->string('slug');
                $table->string('description')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['type', 'slug']);
            });
        }

        $now = now();
        foreach (self::DEFAULTS as $type => $categories) {
            foreach ($categories as $index => [$name, $description]) {
                $slug = Str::slug($name);
                $exists = DB::table('categories')->where('type', $type)->where('slug', $slug)->exists();

                if (! $exists) {
                    DB::table('categories')->insert([
                        'type' => $type,
                        'name' => $name,
                        'slug' => $slug,
                        'description' => $description,
                        'sort_order' => ($index + 1) * 10,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
