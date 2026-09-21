<?php

namespace Database\Seeders;

use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Rate;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RatesAndSuppliersSeeder extends Seeder
{
    /**
     * Seed sample suppliers, rates and a demonstration quotation.
     */
    public function run(): void
    {
        $suppliers = [
            ['name' => 'Kampala Hardware Supplies Ltd', 'region' => 'Kampala', 'phone' => '+256 700 000 001', 'materials' => ['cement', 'aggregates', 'steel']],
            ['name' => 'Civil Works Materials (U) Ltd', 'region' => 'Entebbe', 'phone' => '+256 700 000 002', 'materials' => ['sand', 'hardcore', 'bricks']],
            ['name' => 'Nile Construction Suppliers', 'region' => 'Jinja', 'phone' => '+256 700 000 003', 'materials' => ['roofing', 'paint', 'timber']],
        ];

        $savedSuppliers = [];
        foreach ($suppliers as $data) {
            $savedSuppliers[] = Supplier::create([
                'code' => 'SUP-'.Str::upper(Str::random(6)),
                'name' => $data['name'],
                'contact_name' => $data['name'],
                'phone' => $data['phone'],
                'email' => Str::slug($data['name']).'@example.com',
                'location' => $data['region'],
                'region' => $data['region'],
                'country' => 'UG',
                'currency' => 'UGX',
                'materials' => $data['materials'],
                'preferred_language' => 'en',
                'is_active' => true,
            ]);
        }

        $rates = [
            ['item' => 'Cement 42.5N (50kg)', 'unit' => 'NO', 'rate' => 39000, 'category' => 'materials', 'region' => 'Kampala', 'source_type' => 'market_survey'],
            ['item' => 'Sharp sand (per tonne)', 'unit' => 'tonne', 'rate' => 95000, 'category' => 'materials', 'region' => 'Entebbe', 'source_type' => 'market_survey'],
            ['item' => 'Hardcore (per m3)', 'unit' => 'm3', 'rate' => 60000, 'category' => 'materials', 'region' => 'Kampala', 'source_type' => 'previous_boq'],
            ['item' => 'Reinforcement steel 12mm (per bar)', 'unit' => 'bar', 'rate' => 32000, 'category' => 'materials', 'region' => 'Jinja', 'source_type' => 'supplier_price_list'],
            ['item' => 'Emulsion paint - white 20ltr', 'unit' => 'ltr', 'rate' => 125000, 'category' => 'materials', 'region' => 'Kampala', 'source_type' => 'supplier_price_list'],
        ];

        $user = \App\Models\User::query()->first();

        foreach ($rates as $index => $data) {
            Rate::create(array_merge([
                'code' => 'RATE-'.strtoupper(Str::slug($data['item']).'-'.Str::random(4)),
                'description' => 'Sample rate entry seeded for development.',
                'currency' => 'UGX',
                'country' => 'UG',
                'supplier_id' => $savedSuppliers[$index % count($savedSuppliers)]->id,
                'effective_from' => now()->subDays(15)->toDateString(),
                'verification_status' => 'approved',
                'verified_by' => $user?->id,
                'verified_at' => now()->subDays(10),
                'review_required_at' => now()->addDays(90)->toDateString(),
                'is_active' => true,
                'created_by' => $user?->id,
                'original_language' => 'en',
            ], $data));
        }

        $quotation = Quotation::create([
            'quote_number' => 'QTN-SEED-'.now()->format('Ymd'),
            'supplier_id' => $savedSuppliers[0]->id,
            'status' => 'received',
            'quotation_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'currency' => 'UGX',
            'tax_rate' => 18,
            'discount_amount' => 0,
            'source' => 'manual',
            'source_language' => 'en',
            'notes' => 'Sample quotation created for development.',
            'created_by' => $user?->id,
        ]);

        $lineDefinitions = [
            ['product' => 'Cement 42.5N (50kg)', 'quantity' => 100, 'unit_price' => 37500, 'unit' => 'NO'],
            ['product' => 'Sharp sand (per tonne)', 'quantity' => 20, 'unit_price' => 90000, 'unit' => 'tonne'],
            ['product' => 'Reinforcement steel 12mm (per bar)', 'quantity' => 60, 'unit_price' => 31000, 'unit' => 'bar'],
        ];

        $subtotal = 0;
        foreach ($lineDefinitions as $index => $line) {
            $lineTotal = round($line['quantity'] * $line['unit_price'], 2);
            $subtotal += $lineTotal;
            QuotationItem::create([
                'quotation_id' => $quotation->id,
                'sort_order' => $index,
                'product' => $line['product'],
                'unit' => $line['unit'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'vat_rate' => 18,
                'line_total' => $lineTotal,
                'approved' => false,
            ]);
        }

        $taxAmount = round($subtotal * 0.18, 2);
        $quotation->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $subtotal + $taxAmount,
        ]);
    }
}