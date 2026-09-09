<?php

namespace Database\Seeders;

use App\Models\ProductVersion;
use Illuminate\Database\Seeder;

class ProductVersionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $versions = [
            [
                'version_number' => '1.0',
                'name' => 'Core BOQ Platform',
                'release_notes' => 'Initial release with core BOQ management, projects, subscriptions and multilingual support.',
                'release_date' => now()->toDateString(),
                'classification' => 'major',
                'included_features' => [
                    'project.management',
                    'boq.management',
                    'boq.import.excel',
                    'rate.library',
                    'multilingual',
                ],
                'requires_topup' => false,
                'minimum_supported_version' => null,
                'is_active' => true,
            ],
            [
                'version_number' => '1.5',
                'name' => 'AI Pricing Improvements',
                'release_notes' => 'Improved AI pricing analysis and rate library matching.',
                'release_date' => now()->addMonths(3)->toDateString(),
                'classification' => 'minor',
                'included_features' => [
                    'ai.pricing.analysis',
                    'ai.boq.extraction',
                ],
                'requires_topup' => true,
                'minimum_supported_version' => '1.0',
                'is_active' => true,
            ],
            [
                'version_number' => '2.0',
                'name' => 'Advanced Project Cost Control',
                'release_notes' => 'Variations, actual cost tracking and advanced analytics.',
                'release_date' => now()->addMonths(6)->toDateString(),
                'classification' => 'major',
                'included_features' => [
                    'variations',
                    'cost.tracking',
                    'analytics.advanced',
                ],
                'requires_topup' => true,
                'minimum_supported_version' => '1.0',
                'is_active' => true,
            ],
            [
                'version_number' => '3.0',
                'name' => 'Enterprise Procurement and Payment Certificates',
                'release_notes' => 'Interim payment certificates and enterprise procurement.',
                'release_date' => now()->addMonths(12)->toDateString(),
                'classification' => 'major',
                'included_features' => [
                    'payment.certificates',
                ],
                'requires_topup' => true,
                'minimum_supported_version' => '2.0',
                'is_active' => true,
            ],
        ];

        foreach ($versions as $version) {
            ProductVersion::updateOrCreate(['version_number' => $version['version_number']], $version);
        }
    }
}
