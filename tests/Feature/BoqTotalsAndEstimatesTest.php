<?php

namespace Tests\Feature;

use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\Entitlement;
use App\Models\Feature;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BoqTotalsAndEstimatesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Boq $boq;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.default'));
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->user = User::factory()->create();
        $this->user->roles()->attach(Role::where('slug', 'user')->value('id'));
        Entitlement::factory()->create([
            'user_id' => $this->user->id,
            'organisation_id' => $this->user->organisation_id,
            'feature_id' => Feature::firstOrCreate(['code' => 'boq.management'], ['name' => 'BOQ management'])->id,
            'status' => 'active',
            'expires_at' => now()->addMonth(),
        ]);

        $this->project = Project::factory()->create([
            'user_id' => $this->user->id,
            'organisation_id' => $this->user->organisation_id,
        ]);
        $this->boq = Boq::factory()->create([
            'project_id' => $this->project->id,
            'organisation_id' => $this->user->organisation_id,
        ]);
    }

    private function item(array $attributes): BoqItem
    {
        return BoqItem::factory()->create($attributes + [
            'boq_id' => $this->boq->id,
            'original_rate' => null,
            'ai_suggested_rate' => null,
            'approved_rate' => null,
            'reviewed_rate' => null,
        ]);
    }

    public function test_projects_and_boqs_report_estimated_and_generated_totals(): void
    {
        $this->item(['quantity' => 10, 'original_rate' => 1000, 'ai_suggested_rate' => 1200]);
        $this->item(['quantity' => 2, 'original_rate' => 500, 'approved_rate' => 450, 'ai_suggested_rate' => 600]);
        $this->item(['quantity' => 4, 'original_rate' => null, 'ai_suggested_rate' => null]);

        $api = $this->actingAs($this->user, 'sanctum');

        $api->getJson("/api/v1/boqs/{$this->boq->id}")
            ->assertOk()
            ->assertJsonPath('data.totals.estimated_amount', 11000)
            ->assertJsonPath('data.totals.generated_total', 12900)
            ->assertJsonPath('data.totals.difference', 1900)
            ->assertJsonPath('data.totals.priced_items', 2)
            ->assertJsonPath('data.totals.items', 3);

        $api->getJson('/api/v1/boqs')->assertOk()->assertJsonPath('data.data.0.totals.generated_total', 12900);

        $api->getJson("/api/v1/projects/{$this->project->id}")
            ->assertOk()
            ->assertJsonPath('data.totals.estimated_amount', 11000)
            ->assertJsonPath('data.totals.boqs', 1)
            ->assertJsonPath('data.boqs.0.totals.generated_total', 12900);

        $api->getJson('/api/v1/projects')->assertOk()->assertJsonPath('data.data.0.totals.generated_total', 12900);
    }

    public function test_estimated_prices_file_updates_matching_items(): void
    {
        $byId = $this->item(['item_code' => 'A', 'description' => 'Excavation', 'quantity' => 10, 'amount' => 0]);
        $byCode = $this->item(['item_code' => 'B', 'description' => 'Blinding', 'quantity' => 4, 'amount' => 0]);
        $byText = $this->item(['item_code' => null, 'description' => 'Hardcore  filling', 'quantity' => 2, 'amount' => 0]);

        $template = $this->actingAs($this->user, 'sanctum')
            ->get("/api/v1/boqs/{$this->boq->id}/estimates/template")
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('ID,Item,Description,Unit,Quantity,"Estimated Rate"', $template->getContent());

        $csv = "Estimates for block A\n"
            ."ID,Item,Description,Estimated Rate (UGX)\n"
            ."{$byId->id},,,\"1,500\"\n"
            .",B,,UGX 800\n"
            .",,hardcore filling,45000\n"
            .",Z,Unknown item,99\n"
            .",,Row without a rate,\n";

        $this->actingAs($this->user, 'sanctum')
            ->post("/api/v1/boqs/{$this->boq->id}/estimates", [
                'file' => UploadedFile::fake()->createWithContent('estimates.csv', $csv),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.updated', 3)
            ->assertJsonPath('data.unmatched', ['Unknown item'])
            ->assertJsonPath('data.totals.estimated_amount', 15000 + 3200 + 90000);

        $this->assertEquals([1500, 15000], [$byId->fresh()->original_rate, $byId->fresh()->amount]);
        $this->assertEquals(800, $byCode->fresh()->original_rate);
        $this->assertEquals(45000, $byText->fresh()->original_rate);
        $this->assertTrue($this->boq->summaries()->where('summary_type', 'grand')->exists());
    }

    public function test_estimates_without_a_rate_column_explain_what_is_needed(): void
    {
        $this->item(['description' => 'Excavation']);

        $this->actingAs($this->user, 'sanctum')
            ->post("/api/v1/boqs/{$this->boq->id}/estimates", [
                'file' => UploadedFile::fake()->createWithContent('bad.csv', "Description,Notes\nExcavation,soon\n"),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_project_update_accepts_json_and_clears_fields(): void
    {
        $this->project->update(['client' => 'Old client', 'contract_value' => 100]);

        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/projects/{$this->project->id}", [
                'name' => 'Renamed school',
                'client' => null,
                'contract_value' => 1250000.5,
                'start_date' => '2026-09-01',
                'expected_completion_date' => '2026-12-31',
                'currency' => 'UGX',
                'status' => 'active',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed school');

        $fresh = $this->project->fresh();
        $this->assertNull($fresh->client);
        $this->assertEquals(1250000.5, $fresh->contract_value);
        $this->assertSame('active', $fresh->status);
    }

    public function test_web_project_edit_saves_cleared_fields_and_formatted_amounts(): void
    {
        $this->project->update(['start_date' => '2026-01-01', 'client' => 'Old', 'contract_value' => 10]);

        \Livewire\Livewire::actingAs($this->user)
            ->test(\App\Livewire\Projects\Edit::class, ['project' => $this->project])
            ->set('name', 'Updated project')
            ->set('client', '')
            ->set('startDate', '')
            ->set('expectedCompletionDate', '')
            ->set('contractValue', '1,250,000')
            ->set('currency', 'ugx')
            ->call('save')
            ->assertHasNoErrors();

        $fresh = $this->project->fresh();
        $this->assertSame('Updated project', $fresh->name);
        $this->assertNull($fresh->client);
        $this->assertNull($fresh->start_date);
        $this->assertEquals(1250000, $fresh->contract_value);
        $this->assertSame('UGX', $fresh->currency);
    }

    public function test_web_boq_page_shows_totals_and_accepts_an_estimates_file(): void
    {
        $item = $this->item(['description' => 'Excavation', 'quantity' => 10]);

        \Livewire\Livewire::actingAs($this->user)
            ->test(\App\Livewire\Boqs\Show::class, ['boq' => $this->boq])
            ->assertSee('Estimated amount')
            ->set('estimatesFile', UploadedFile::fake()->createWithContent('rates.csv', "Description,Estimated Rate\nExcavation,1500\n"))
            ->call('uploadEstimates')
            ->assertHasNoErrors();

        $this->assertEquals(1500, $item->fresh()->original_rate);
    }
}
