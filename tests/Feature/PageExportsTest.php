<?php

namespace Tests\Feature;

use App\Livewire\Expenses\Index;
use App\Models\Expense;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\TableExportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

class PageExportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_export_includes_all_matching_rows_preserves_pagination_and_respects_visibility(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', 'user')->value('id'), ['organisation_id' => $user->organisation_id]);
        $project = Project::factory()->assignedTo($user)->create(['organisation_id' => $user->organisation_id]);
        Expense::factory()->count(23)->create(['project_id' => $project->id, 'organisation_id' => $user->organisation_id,
            'creator_user_id' => $user->id, 'description' => 'Matching cement']);
        Expense::factory()->create(['project_id' => $project->id, 'organisation_id' => $user->organisation_id,
            'creator_user_id' => $user->id, 'description' => 'Different item']);
        Expense::factory()->create(['project_id' => $project->id, 'organisation_id' => $user->organisation_id, 'description' => 'Matching hidden expense']);
        $this->mock(TableExportService::class)->shouldReceive('download')->once()->withArgs(function ($sections, $format) {
            $this->assertSame('csv', $format);
            $this->assertCount(23, $sections[0]['rows']);
            $this->assertSame('Matching cement', $sections[0]['rows'][0][3]);

            return true;
        })->andReturn(response()->streamDownload(fn () => print('export'), 'expenses.csv'));
        Livewire::actingAs($user)->test(Index::class)->assertSee("exportTables('csv')", false)
            ->set('search', 'Matching')->call('setPage', 2)->call('exportTables', 'csv')->assertFileDownloaded('expenses.csv')
            ->assertSet('paginators.page', 2)->assertSet('search', 'Matching');
    }

    public function test_csv_escapes_formulas_and_pdf_is_generated_from_escaped_text(): void
    {
        $sections = [['title' => 'Test export', 'columns' => ['name' => 'Name', 'amount' => 'Amount'],
            'rows' => [['=HYPERLINK("https://example.com")', 25], ['<script>alert(1)</script>Safe', 30]]]];
        $csv = TestResponse::fromBaseResponse(app(TableExportService::class)->download($sections, 'csv'))->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('<script>', $csv);
        $pdf = TestResponse::fromBaseResponse(app(TableExportService::class)->download($sections, 'pdf'))->streamedContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
    }

    public function test_non_admin_cannot_export_admin_users(): void
    {
        Livewire::actingAs(User::factory()->create())->test(\App\Livewire\Admin\UsersManager::class)->assertForbidden();
    }

    public function test_all_registered_data_pages_can_generate_an_export(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', 'super-admin')->value('id'), ['organisation_id' => $user->organisation_id]);
        $project = Project::factory()->assignedTo($user)->create(['user_id' => $user->id, 'organisation_id' => $user->organisation_id]);
        $boq = \App\Models\Boq::factory()->create(['project_id' => $project->id, 'organisation_id' => $user->organisation_id]);
        $price = \App\Models\HardwarePrice::factory()->create(['organisation_id' => $user->organisation_id]);
        $otherPrice = \App\Models\HardwarePrice::factory()->create(['organisation_id' => $user->organisation_id]);
        $this->mock(TableExportService::class)->shouldReceive('download')->times(count(config('page-exports')))
            ->andReturn(response()->streamDownload(fn () => print('export'), 'data.csv'));
        foreach (array_keys(config('page-exports')) as $class) {
            $parameters = match ($class) {
                \App\Livewire\Projects\Show::class => ['project' => $project],
                \App\Livewire\Boqs\Show::class => ['boq' => $boq],
                \App\Livewire\HardwarePrices\Show::class => ['hardwarePrice' => $price],
                default => [],
            };
            $component = Livewire::actingAs($user)->test($class, $parameters)->assertSee("exportTables('csv')", false);
            if ($class === \App\Livewire\HardwarePrices\Compare::class) {
                $component->set('selectedIds', [$price->id, $otherPrice->id]);
            }
            $component->call('exportTables', 'csv')->assertFileDownloaded('data.csv');
        }
    }
}
