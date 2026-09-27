<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Global (organisation-less) building material categories with their default items.
 *
 * Idempotent: missing categories are inserted; existing ones (matched by slug) keep
 * their admin edits and only gain any default items they are missing.
 */
class BuildingCategoriesSeeder extends Seeder
{
    /**
     * @return list<array{0: string, 1: string, 2: list<string>}>
     */
    public static function categories(): array
    {
        return [
            ['Cement', 'Cement and cement products', ['Portland Cement 42.5N', 'Portland Cement 32.5R', 'Waterproof Cement', 'White Cement', 'Masonry Cement']],
            ['Aggregates', 'Sand, gravel, stone and fill', ['Lake Sand', 'Plaster Sand', 'Pit Sand', 'Gravel', 'Crushed Stone (Aggregate)', 'Hardcore', 'Murram', 'Stone Dust']],
            ['Ready-Mix Concrete & Precast', 'Ready-mix concrete and precast elements', ['Ready-Mix Concrete C20', 'Ready-Mix Concrete C25', 'Ready-Mix Concrete C30', 'Precast Slabs', 'Culverts', 'Kerbs', 'Paving Blocks']],
            ['Steel', 'Reinforcement and structural steel', ['Deformed Bars (Y8-Y32)', 'Round Bars (R6-R12)', 'Binding Wire', 'BRC Mesh', 'Hollow Sections', 'Angle Lines', 'Channels', 'I-Beams', 'Steel Plates']],
            ['Bricks & Blocks', 'Bricks, blocks and masonry units', ['Burnt Clay Bricks', 'Concrete Blocks', 'Hollow Blocks', 'Interlocking Soil-Stabilised Blocks', 'Ventilation Blocks']],
            ['Timber', 'Structural timber and sheet boards', ['Treated Timber', 'Cypress', 'Pine', 'Hardwood (Mvule)', 'Plywood', 'MDF', 'Blockboard', 'Chipboard', 'Formwork Timber']],
            ['Roofing', 'Roofing sheets, tiles and accessories', ['Iron Sheets (Gauge 28)', 'Iron Sheets (Gauge 30)', 'Pre-painted Sheets', 'Clay Roofing Tiles', 'Stone-coated Tiles', 'Ridges', 'Valleys', 'Roofing Nails', 'Gutters', 'Downpipes', 'Fascia Boards']],
            ['Waterproofing & Insulation', 'Damp proofing, membranes and insulation', ['DPC', 'DPM Polythene', 'Bituminous Membrane', 'Waterproofing Admixture', 'Roof Insulation', 'Sarking Felt']],
            ['Doors & Windows', 'Doors, frames, windows and glazing', ['Flush Doors', 'Panel Doors', 'Steel Doors', 'Door Frames', 'Steel Casement Windows', 'Aluminium Windows', 'Glass Panes', 'Burglar Proofing']],
            ['Hardware', 'Fixings and general hardware', ['Nails', 'Screws', 'Bolts & Nuts', 'Hinges', 'Locks', 'Door Handles', 'Padlocks', 'Anchor Bolts']],
            ['Plumbing', 'Pipes, fittings and sanitary installation', ['PVC Pipes', 'PPR Pipes', 'HDPE Pipes', 'GI Pipes', 'Pipe Fittings', 'Gate Valves', 'Taps & Mixers', 'Water Tanks', 'Water Meters']],
            ['Sanitary Ware', 'Toilets, basins and bathroom fittings', ['WC Suites', 'Wash Hand Basins', 'Kitchen Sinks', 'Showers', 'Urinals', 'Bathroom Accessories']],
            ['Drainage & Sewerage', 'Drainage pipes, manholes and fittings', ['uPVC Drainage Pipes', 'Concrete Pipes', 'Manhole Covers', 'Gully Traps', 'Inspection Chambers', 'Septic Tank Materials']],
            ['Electrical', 'Cables, wiring devices and accessories', ['Cables (1.5-16mm)', 'Armoured Cables', 'Conduits', 'Switches', 'Sockets', 'Distribution Boards', 'Circuit Breakers', 'Light Fittings', 'Earthing Materials']],
            ['Solar & Power', 'Solar, backup power and generators', ['Solar Panels', 'Inverters', 'Batteries', 'Charge Controllers', 'Generators', 'Street Lights']],
            ['Paint', 'Paints, primers and finishes', ['Emulsion Paint', 'Gloss (Oil) Paint', 'Weather Guard', 'Primer', 'Undercoat', 'Varnish', 'Thinner', 'Anti-rust Paint']],
            ['Tiles', 'Floor and wall finishes', ['Ceramic Tiles', 'Porcelain Tiles', 'Terrazzo', 'Vinyl Flooring', 'Wooden Flooring', 'Skirting', 'Tile Trims']],
            ['Adhesives', 'Construction adhesives, grouts and sealants', ['Tile Adhesive', 'Grout', 'Silicone Sealant', 'Construction Adhesive', 'Epoxy', 'Expansion Joint Filler']],
            ['Ceilings & Partitions', 'Ceiling boards and drywall systems', ['Gypsum Boards', 'PVC Ceiling Panels', 'Ceiling Brandering', 'Metal Studs & Tracks', 'Cornices']],
            ['Glass & Aluminium', 'Glazing, aluminium sections and curtain walling', ['Clear Glass', 'Tinted Glass', 'Tempered Glass', 'Aluminium Sections', 'Mirrors']],
            ['Fencing & External Works', 'Fencing, gates and landscaping', ['Chain-link Fence', 'Barbed Wire', 'Razor Wire', 'Fence Posts', 'Steel Gates', 'Paving Slabs', 'Kerbstones']],
            ['Road Works', 'Road construction materials', ['Bitumen', 'Asphalt Concrete', 'Prime Coat', 'Road Marking Paint', 'Road Signs', 'Guard Rails']],
            ['Chemicals & Admixtures', 'Concrete admixtures and construction chemicals', ['Plasticiser', 'Retarder', 'Accelerator', 'Curing Compound', 'Bonding Agent', 'Anti-termite Treatment']],
            ['Plant & Equipment Hire', 'Construction plant and equipment', ['Concrete Mixer', 'Poker Vibrator', 'Plate Compactor', 'Excavator', 'Tipper Truck', 'Scaffolding', 'Formwork Props']],
            ['Tools', 'Hand and power tools', ['Trowels', 'Spirit Levels', 'Measuring Tapes', 'Wheelbarrows', 'Shovels & Hoes', 'Drills', 'Angle Grinders']],
            ['Safety', 'Personal protective equipment and site safety', ['Helmets', 'Safety Boots', 'Gloves', 'Reflective Vests', 'Safety Harness', 'Goggles', 'Safety Nets', 'First Aid Kits']],
            ['Labour', 'Labour rates by trade', ['Mason', 'Carpenter', 'Steel Fixer', 'Plumber', 'Electrician', 'Painter', 'Tiler', 'Porter / Casual Labourer']],
        ];
    }

    public function run(): void
    {
        $now = now();
        $hasSlug = Schema::hasColumn('hardware_categories', 'slug');
        $hasOrganisation = Schema::hasColumn('hardware_categories', 'organisation_id');

        foreach (self::categories() as $index => [$name, $description, $items]) {
            $slug = Str::slug($name);

            $existing = DB::table('hardware_categories')
                ->when($hasSlug, fn ($q) => $q->where('slug', $slug), fn ($q) => $q->where('name', $name))
                ->when($hasOrganisation, fn ($q) => $q->whereNull('organisation_id'))
                ->first();

            if ($existing) {
                // Keep admin edits; only add default items that are missing.
                $current = json_decode((string) $existing->default_items, true);
                $current = is_array($current) ? $current : [];
                $merged = array_values(array_unique(array_merge($current, $items)));

                if (count($merged) !== count($current)) {
                    DB::table('hardware_categories')
                        ->where('id', $existing->id)
                        ->update(['default_items' => json_encode($merged), 'updated_at' => $now]);
                }

                continue;
            }

            $row = [
                'name' => $name,
                'description' => $description,
                'default_items' => json_encode($items),
                'is_active' => true,
                'sort_order' => ($index + 1) * 10,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($hasSlug) {
                $row['slug'] = $slug;
            }

            DB::table('hardware_categories')->insert($row);
        }
    }
}
