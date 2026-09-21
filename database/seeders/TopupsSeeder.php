<?php

namespace Database\Seeders;

use App\Models\Topup;
use Illuminate\Database\Seeder;

class TopupsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $updateEligiblePlans = [
            'one-time',
            'monthly-professional',
            'three-month-professional',
            'six-month-professional',
            'annual-professional',
            'lifetime',
        ];

        $topups = [
            // Usage-credit top-ups
            [
                'name' => 'AI Credit Pack (50)',
                'code' => 'ai-credits-50',
                'description' => 'Adds 50 AI credits for BOQ extraction and pricing.',
                'type' => 'ai_credit_topup',
                'price' => 5000,
                'currency' => 'UGX',
                'is_permanent' => false,
                'duration_days' => 30,
                'usage_credits' => ['ai_credits' => 50],
                'purchase_limit' => null,
                'display_order' => 1,
            ],
            [
                'name' => 'OCR Pack (100 pages)',
                'code' => 'ocr-pages-100',
                'description' => 'Adds 100 reusable OCR scan pages.',
                'type' => 'ocr_credit_topup',
                'price' => 5000,
                'currency' => 'UGX',
                'is_permanent' => false,
                'duration_days' => 30,
                'usage_credits' => ['ocr_pages' => 100],
                'purchase_limit' => null,
                'display_order' => 2,
            ],
            [
                'name' => 'Translation Pack (500)',
                'code' => 'translations-500',
                'description' => 'Adds 500 translation records.',
                'type' => 'translation_credit_topup',
                'price' => 5000,
                'currency' => 'UGX',
                'is_permanent' => false,
                'duration_days' => 30,
                'usage_credits' => ['translations' => 500],
                'purchase_limit' => null,
                'display_order' => 3,
            ],
            [
                'name' => 'Storage Pack (1 GB)',
                'code' => 'storage-1gb',
                'description' => 'Adds 1 GB of document storage.',
                'type' => 'storage_topup',
                'price' => 10000,
                'currency' => 'UGX',
                'is_permanent' => true,
                'usage_credits' => ['storage_bytes' => 1073741824],
                'purchase_limit' => null,
                'display_order' => 4,
            ],
            [
                'name' => 'Extra Team Seat',
                'code' => 'user-seat-1',
                'description' => 'Adds one extra team member seat.',
                'type' => 'user_seat_topup',
                'price' => 15000,
                'currency' => 'UGX',
                'is_permanent' => false,
                'duration_days' => 30,
                'usage_credits' => ['user_seats' => 1],
                'purchase_limit' => null,
                'display_order' => 5,
            ],

            // Feature-unlock / version-update top-ups
            [
                'name' => 'Variations Module',
                'code' => 'unlock-variations',
                'description' => 'Unlocks the Variations module on active projects.',
                'type' => 'feature_unlock',
                'price' => 50000,
                'currency' => 'UGX',
                'is_permanent' => true,
                'included_features' => ['variations'],
                'applicable_plans' => $updateEligiblePlans,
                'requires_confirmation' => true,
                'display_order' => 20,
            ],
            [
                'name' => 'Actual Cost Tracking',
                'code' => 'unlock-cost-tracking',
                'description' => 'Unlocks actual cost tracking on projects.',
                'type' => 'feature_unlock',
                'price' => 50000,
                'currency' => 'UGX',
                'is_permanent' => true,
                'included_features' => ['cost.tracking'],
                'applicable_plans' => $updateEligiblePlans,
                'requires_confirmation' => true,
                'display_order' => 21,
            ],
            [
                'name' => 'Interim Payment Certificates',
                'code' => 'unlock-payment-certificates',
                'description' => 'Unlocks interim payment certificates.',
                'type' => 'feature_unlock',
                'price' => 75000,
                'currency' => 'UGX',
                'is_permanent' => true,
                'included_features' => ['payment.certificates'],
                'applicable_plans' => $updateEligiblePlans,
                'requires_confirmation' => true,
                'display_order' => 22,
            ],
            [
                'name' => 'Version 1.5 Update (AI Pricing)',
                'code' => 'update-v1-5',
                'description' => 'Minor update: improved AI pricing and extraction.',
                'type' => 'version_update',
                'price' => 0,
                'currency' => 'UGX',
                'is_permanent' => true,
                'release_version' => '1.5',
                'included_features' => ['ai.pricing.analysis', 'ai.boq.extraction'],
                'applicable_plans' => $updateEligiblePlans,
                'requires_confirmation' => true,
                'display_order' => 30,
            ],
            [
                'name' => 'Version 2.0 Update (Advanced Project Cost Control)',
                'code' => 'update-v2-0',
                'description' => 'Major update: variations, actual cost tracking and advanced analytics.',
                'type' => 'version_update',
                'price' => 150000,
                'currency' => 'UGX',
                'is_permanent' => true,
                'release_version' => '2.0',
                'included_features' => ['variations', 'cost.tracking', 'analytics.advanced'],
                'applicable_plans' => $updateEligiblePlans,
                'requires_confirmation' => true,
                'display_order' => 31,
            ],
            [
                'name' => 'Version 3.0 Update (Enterprise Procurement)',
                'code' => 'update-v3-0',
                'description' => 'Major update: interim payment certificates and enterprise procurement.',
                'type' => 'version_update',
                'price' => 250000,
                'currency' => 'UGX',
                'is_permanent' => true,
                'release_version' => '3.0',
                'included_features' => ['payment.certificates'],
                'applicable_plans' => $updateEligiblePlans,
                'requires_confirmation' => true,
                'display_order' => 32,
            ],
        ];

        foreach ($topups as $topup) {
            Topup::updateOrCreate(['code' => $topup['code']], $topup);
        }
    }
}