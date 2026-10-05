<?php

namespace Tests\Feature;

use App\Models\Entitlement;
use App\Models\Feature;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use Tests\TestCase;

class XlsxApiImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_flow_uploads_processes_and_lists_xlsx_items(): void
    {
        Storage::fake(config('filesystems.default'));
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', 'user')->value('id'));
        foreach (['boq.management', 'boq.import.excel'] as $code) {
            Entitlement::factory()->create([
                'user_id' => $user->id,
                'organisation_id' => $user->organisation_id,
                'feature_id' => Feature::firstOrCreate(['code' => $code], ['name' => $code])->id,
                'status' => 'active',
                'expires_at' => now()->addMonth(),
            ]);
        }
        $project = Project::factory()->assignedTo($user)->create(['user_id' => $user->id, 'organisation_id' => $user->organisation_id]);

        $xlsx = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        $writer = new \OpenSpout\Writer\XLSX\Writer();
        $writer->openToFile($xlsx);
        $writer->addRow(Row::fromValues(['PROPOSED CLASSROOM BLOCK']));
        $writer->addRow(Row::fromValues(['BILL No. 1 - SUBSTRUCTURE']));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['ITEM', 'DESCRIPTION', 'UNIT', 'QTY', 'RATE (UGX)', 'AMOUNT (UGX)']));
        $writer->addRow(Row::fromValues(['A', 'Excavation to foundations', 'm3', 10, 1500, '=D5*E5']));
        $writer->addRow(Row::fromValues(['B', 'Blinding concrete', 'm2', 5.5, 800, '=D6*E6']));
        $writer->addRow(Row::fromValues(['', 'Carried to summary', '', '', '', '=SUM(F5:F6)']));
        $writer->close();

        $upload = $this->actingAs($user, 'sanctum')->post('/api/v1/boqs', [
            'project_id' => $project->id,
            'name' => 'Main BOQ',
            'file' => new UploadedFile($xlsx, 'Main BOQ.xlsx', null, null, true),
        ], ['Accept' => 'application/json']);
        $upload->assertCreated();
        $id = $upload->json('data.id');

        $process = $this->actingAs($user, 'sanctum')->postJson("/api/v1/boqs/{$id}/process");
        $process->assertOk()->assertJsonPath('meta.items_imported', 2);

        $this->actingAs($user, 'sanctum')->getJson("/api/v1/boqs/{$id}/items?page=1&per_page=100")
            ->assertOk()
            ->assertJsonCount(2, 'data.data');
        $this->actingAs($user, 'sanctum')->getJson("/api/v1/boqs/{$id}")->assertOk();
    }

    public function test_sections_lump_sums_and_formatted_numbers_are_understood(): void
    {
        Storage::fake(config('filesystems.default'));
        $user = User::factory()->create();
        $project = Project::factory()->assignedTo($user)->create(['user_id' => $user->id, 'organisation_id' => $user->organisation_id]);

        $xlsx = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        $writer = new \OpenSpout\Writer\XLSX\Writer();
        $writer->openToFile($xlsx);
        foreach ([
            ['Item No.', 'Description of Works', 'Unit', 'Qty.', 'Rate (UGX)', 'Amount (UGX)'],
            ['', 'BLOCK A: CLASSROOMS', '', '', '', ''],
            ['', 'BILL No. 1 - SUBSTRUCTURE', '', '', '', ''],
            ['', 'ELEMENT 1: EXCAVATION', '', '', '', ''],
            ['', 'Rates to include for disposal of surplus material.', '', '', '', ''],
            ['A', 'Excavate topsoil', '', '10 m2', 'UGX 1,500', ''],
            ['B', 'Provisional sum for termite treatment', 'Item', 'Item', '', '2,000,000/='],
            ['', 'Carried to collection', '', '', '', '2,015,000'],
            ['Item No.', 'Description of Works', 'Unit', 'Qty.', 'Rate (UGX)', 'Amount (UGX)'],
            ['C', 'Hardcore filling', 'm3', '-', '45,000', '-'],
            ['D', 'Concrete blinding', 'm2', '5.5', '800', ''],
        ] as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        $boq = \App\Models\Boq::factory()->create([
            'project_id' => $project->id,
            'organisation_id' => $user->organisation_id,
            'source_type' => 'excel',
            'source_file_path' => Storage::putFileAs("boqs/{$project->id}", new \Illuminate\Http\File($xlsx), 'real.xlsx'),
        ]);

        $this->assertSame(4, app(\App\Services\BoqSpreadsheetImporter::class)->import($boq));

        $items = $boq->items()->orderBy('id')->get();
        $this->assertSame(['A', 'B', 'C', 'D'], $items->pluck('item_code')->all());
        $this->assertEquals([10, 1500, 15000], [$items[0]->quantity, $items[0]->original_rate, $items[0]->amount]);
        $this->assertSame('m2', $items[0]->unit);
        $this->assertEquals([1, 2000000, 2000000], [$items[1]->quantity, $items[1]->original_rate, $items[1]->amount]);
        $this->assertEquals([1, 45000], [$items[2]->quantity, $items[2]->original_rate]);
        $this->assertEquals(4400, $items[3]->amount);

        $this->assertSame(['BLOCK A: CLASSROOMS'], $boq->facilities()->pluck('name')->all());
        $this->assertNotNull($items[0]->element_id);
        $this->assertSame($items[0]->bill_id, $items[3]->bill_id);
    }

    public function test_mobile_process_extracts_pdf_boqs_with_the_ai_provider(): void
    {
        Storage::fake(config('filesystems.default'));
        config()->set('services.ai_provider', 'gemini');
        config()->set('services.gemini.key', 'gemini-test-key');
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode(['items' => [[
                'item_code' => 'A1', 'description' => 'Concrete foundation', 'unit' => 'm3',
                'quantity' => 2, 'original_rate' => 10, 'amount' => 20,
            ]], 'warnings' => []])]]]]],
        ])]);
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', 'user')->value('id'));
        foreach (['boq.management', 'boq.import.excel'] as $code) {
            Entitlement::factory()->create([
                'user_id' => $user->id,
                'organisation_id' => $user->organisation_id,
                'feature_id' => Feature::firstOrCreate(['code' => $code], ['name' => $code])->id,
                'status' => 'active',
                'expires_at' => now()->addMonth(),
            ]);
        }
        $project = Project::factory()->assignedTo($user)->create(['user_id' => $user->id, 'organisation_id' => $user->organisation_id]);

        $upload = $this->actingAs($user, 'sanctum')->post('/api/v1/boqs', [
            'project_id' => $project->id,
            'file' => UploadedFile::fake()->createWithContent('site boq.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF"),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/boqs/'.$upload->json('data.id').'/process')
            ->assertOk()
            ->assertJsonPath('meta.items_imported', 1)
            ->assertJsonPath('meta.provider', 'gemini');
        $this->assertDatabaseHas('boq_items', ['description' => 'Concrete foundation']);
    }

    public function test_headings_split_over_two_rows_and_sheets_without_quantities_import(): void
    {
        Storage::fake(config('filesystems.default'));
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'organisation_id' => $user->organisation_id]);
        $csv = "Item,Description of works,Unit Rate,Total\n,,(UGX),(UGX)\n1,Mobilisation,,2500000\n2,Site clearance,1000,\n";
        Storage::put("boqs/{$project->id}/split.csv", $csv);
        $boq = \App\Models\Boq::factory()->create([
            'project_id' => $project->id,
            'organisation_id' => $user->organisation_id,
            'source_type' => 'excel',
            'source_file_path' => "boqs/{$project->id}/split.csv",
        ]);

        $this->assertSame(2, app(\App\Services\BoqSpreadsheetImporter::class)->import($boq));
        $this->assertEquals([2500000, 1000], $boq->items()->orderBy('id')->pluck('amount')->map(fn ($a) => (float) $a)->all());
    }

    public function test_alternative_header_names_are_recognized(): void
    {
        Storage::fake(config('filesystems.default'));
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'organisation_id' => $user->organisation_id]);
        $csv = "S/No,Material,Unit of Measurement,Nos,Unit Cost,Total Cost\n1,Cement 50kg,bags,2,1500,3000\n2,Sand,m3,3,400,\n";
        Storage::put("boqs/{$project->id}/alt.csv", $csv);
        $boq = \App\Models\Boq::factory()->create([
            'project_id' => $project->id,
            'organisation_id' => $user->organisation_id,
            'source_type' => 'excel',
            'source_file_path' => "boqs/{$project->id}/alt.csv",
        ]);

        $this->assertSame(2, app(\App\Services\BoqSpreadsheetImporter::class)->import($boq));

        $items = $boq->items()->orderBy('id')->get();
        $this->assertEquals(['Cement 50kg', 'Sand'], $items->pluck('description')->all());
        $this->assertEquals([2, 1500, 3000], [$items[0]->quantity, $items[0]->original_rate, $items[0]->amount]);
        $this->assertSame('bags', $items[0]->unit);
        $this->assertEquals([3, 400, 1200], [$items[1]->quantity, $items[1]->original_rate, $items[1]->amount]);
    }

    public function test_item_column_is_treated_as_description_when_serial_number_present(): void
    {
        Storage::fake(config('filesystems.default'));
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'organisation_id' => $user->organisation_id]);
        $csv = "S/No,Item,Unit,Quantity,Unit Cost,Total\n1,Cement 50kg,bags,2,1500,3000\n2,Sand,m3,3,400,1200\n";
        Storage::put("boqs/{$project->id}/itemdesc.csv", $csv);
        $boq = \App\Models\Boq::factory()->create([
            'project_id' => $project->id,
            'organisation_id' => $user->organisation_id,
            'source_type' => 'excel',
            'source_file_path' => "boqs/{$project->id}/itemdesc.csv",
        ]);

        $this->assertSame(2, app(\App\Services\BoqSpreadsheetImporter::class)->import($boq));

        $items = $boq->items()->orderBy('id')->get();
        $this->assertEquals(['Cement 50kg', 'Sand'], $items->pluck('description')->all());
        $this->assertEquals(['1', '2'], $items->pluck('item_code')->all());
        $this->assertEquals([2, 1500, 3000], [$items[0]->quantity, $items[0]->original_rate, $items[0]->amount]);
        $this->assertSame('bags', $items[0]->unit);
    }
}
