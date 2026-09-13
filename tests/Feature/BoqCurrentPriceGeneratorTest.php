<?php

namespace Tests\Feature;

use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\HardwarePrice;
use App\Models\Organisation;
use App\Models\Project;
use App\Models\User;
use App\Services\BoqCurrentPriceGenerator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BoqCurrentPriceGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_item_rates_from_current_prices_for_the_project_location(): void
    {
        [$organisation, $user, $boq] = $this->makeBoq();

        $item = BoqItem::factory()->create([
            'boq_id' => $boq->id,
            'description' => 'Portland cement 50kg bag',
            'unit' => 'bag',
            'quantity' => 4,
            'currency' => 'UGX',
            'approved_rate' => null,
        ]);

        $price = HardwarePrice::create([
            'organisation_id' => $organisation->id,
            'item_name' => 'Portland cement 50kg bag',
            'category' => 'Cement',
            'unit' => 'bag',
            'price' => 42000,
            'currency' => 'UGX',
            'supplier' => 'Kampala Hardware',
            'location' => 'Kampala',
            'fetched_at' => now(),
            'is_active' => true,
        ]);

        $summary = app(BoqCurrentPriceGenerator::class)->generate(
            $boq,
            $user->id,
            $organisation->id
        );

        $item->refresh();

        $this->assertSame(1, $summary['matched']);
        $this->assertSame(0, $summary['unmatched']);
        $this->assertSame('42000.00', $item->ai_suggested_rate);
        $this->assertSame($price->id, $item->hardware_price_id);
        $this->assertSame('automatic', $item->match_type);
        $this->assertNull($item->matched_by);
        $this->assertNotNull($item->matched_at);
        $this->assertNull($item->approved_rate);
        $this->assertSame('0.00', $item->amount);
        $this->assertSame('Kampala', $item->location);
        $this->assertStringStartsWith('hardware_price:', $item->pricing_source);
        $this->assertSame('pending', $item->status);
        $this->assertSame('under_review', $boq->fresh()->status);
    }

    public function test_it_does_not_use_prices_from_another_location_or_tenant(): void
    {
        [$organisation, $user, $boq] = $this->makeBoq();
        $otherOrganisation = Organisation::factory()->create();

        BoqItem::factory()->create([
            'boq_id' => $boq->id,
            'description' => 'Roofing sheet',
            'unit' => 'sheet',
            'currency' => 'UGX',
            'original_rate' => null,
            'ai_suggested_rate' => 50000,
            'approved_rate' => 50000,
            'pricing_source' => 'hardware_price:999',
            'hardware_price_id' => null,
            'match_type' => 'automatic',
            'matched_at' => now(),
            'location' => 'Kampala',
            'status' => 'reviewed',
        ]);

        foreach ([[$organisation->id, 'Gulu'], [$otherOrganisation->id, 'Kampala']] as [$organisationId, $location]) {
            HardwarePrice::create([
                'organisation_id' => $organisationId,
                'item_name' => 'Roofing sheet',
                'category' => 'Roofing',
                'unit' => 'sheet',
                'price' => 50000,
                'currency' => 'UGX',
                'supplier' => 'Supplier',
                'location' => $location,
                'fetched_at' => now(),
                'is_active' => true,
            ]);
        }

        $summary = app(BoqCurrentPriceGenerator::class)->generate($boq, $user->id, $organisation->id);

        $this->assertSame(0, $summary['matched']);
        $this->assertSame(1, $summary['unmatched']);
        $unmatched = $boq->items()->first();
        $this->assertNull($unmatched->approved_rate);
        $this->assertNull($unmatched->pricing_source);
        $this->assertNull($unmatched->hardware_price_id);
        $this->assertNull($unmatched->match_type);
        $this->assertNull($unmatched->matched_at);
        $this->assertSame('pending', $unmatched->status);
    }

    public function test_it_imports_a_csv_before_generating_prices(): void
    {
        Storage::fake('local');
        [$organisation, $user, $boq] = $this->makeBoq([
            'source_file_path' => 'boqs/import.csv',
        ]);
        Storage::disk('local')->put(
            'boqs/import.csv',
            "Item,Description,Unit,Quantity\n1,Portland cement 50kg bag,bag,3\n"
        );

        HardwarePrice::create([
            'organisation_id' => $organisation->id,
            'item_name' => 'Portland cement 50kg bag',
            'category' => 'Cement',
            'unit' => 'bag',
            'price' => 40000,
            'currency' => 'UGX',
            'supplier' => 'Kampala Hardware',
            'location' => 'Kampala',
            'fetched_at' => now(),
            'is_active' => true,
        ]);

        $summary = app(BoqCurrentPriceGenerator::class)->generate($boq, $user->id, $organisation->id);

        $this->assertSame(1, $summary['parsed']);
        $this->assertSame(1, $summary['matched']);
        $this->assertDatabaseHas('boq_items', [
            'boq_id' => $boq->id,
            'description' => 'Portland cement 50kg bag',
            'ai_suggested_rate' => 40000,
            'approved_rate' => null,
            'amount' => 0,
        ]);
    }

    public function test_it_extracts_a_pdf_before_generating_prices(): void
    {
        Storage::fake('local');
        config()->set('services.ai_provider', 'gemini');
        config()->set('services.gemini.key', 'gemini-test-key');
        [$organisation, $user, $boq] = $this->makeBoq([
            'source_type' => 'pdf',
            'source_file_path' => 'boqs/import.pdf',
        ]);
        Storage::disk('local')->put('boqs/import.pdf', "%PDF-1.4\n%%EOF");
        Http::fake(['*' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => json_encode([
                    'items' => [[
                        'item_code' => 'P1',
                        'description' => 'Unmatched PDF item',
                        'unit' => 'item',
                        'quantity' => 2,
                        'original_rate' => 15,
                        'amount' => null,
                    ]],
                    'warnings' => [],
                ])]]],
            ]],
        ])]);

        $summary = app(BoqCurrentPriceGenerator::class)->generate($boq, $user->id, $organisation->id);

        $this->assertSame(1, $summary['parsed']);
        $this->assertSame(0, $summary['matched']);
        $this->assertSame(1, $summary['unmatched']);
        $this->assertDatabaseHas('boq_items', [
            'boq_id' => $boq->id,
            'description' => 'Unmatched PDF item',
            'approved_rate' => null,
            'amount' => 30,
        ]);
        $this->assertSame('under_review', $boq->fresh()->status);
    }

    public function test_users_cannot_generate_another_tenants_boq(): void
    {
        [, , $boq] = $this->makeBoq();
        $otherUser = User::factory()->create();

        $this->expectException(AuthorizationException::class);

        app(BoqCurrentPriceGenerator::class)->generate(
            $boq,
            $otherUser->id,
            $otherUser->organisation_id
        );
    }

    public function test_null_organisation_ids_do_not_grant_access_to_another_users_boq(): void
    {
        [, $owner, $boq] = $this->makeBoq();
        $otherUser = User::factory()->create();

        $owner->forceFill(['organisation_id' => null])->save();
        $otherUser->forceFill(['organisation_id' => null])->save();
        $boq->project->forceFill(['organisation_id' => null])->save();
        $boq->forceFill(['organisation_id' => null])->save();

        $this->expectException(AuthorizationException::class);

        app(BoqCurrentPriceGenerator::class)->generate($boq, $otherUser->id, null);
    }

    /**
     * @return array{Organisation, User, Boq}
     */
    private function makeBoq(array $attributes = []): array
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create(['organisation_id' => $organisation->id]);
        $project = Project::factory()->create([
            'organisation_id' => $organisation->id,
            'user_id' => $user->id,
            'location' => 'Kampala',
            'currency' => 'UGX',
        ]);
        $boq = Boq::factory()->create(array_merge([
            'organisation_id' => $organisation->id,
            'project_id' => $project->id,
            'source_type' => 'excel',
            'currency' => 'UGX',
        ], $attributes));

        return [$organisation, $user, $boq];
    }
}
