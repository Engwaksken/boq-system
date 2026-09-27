<?php

namespace Tests\Feature;

use App\Livewire\Boqs\Create as BoqsCreate;
use App\Models\Boq;
use App\Models\Entitlement;
use App\Models\Feature;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BoqWebUploadTest extends TestCase
{
    use RefreshDatabase;

    private function entitle(User $user, string $code, ?array $limits = null): Entitlement
    {
        return Entitlement::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'feature_id' => Feature::firstOrCreate(['code' => $code], ['name' => $code])->id,
            'status' => 'active',
            'expires_at' => now()->addMonth(),
            'limits' => $limits,
            'usage' => [],
        ]);
    }

    private function csv(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'site-works.csv',
            "Item Code,Description,Unit,Quantity,Rate,Amount\nA1,Excavation to foundations,m3,12,15000,180000\nA2,Concrete blinding 50mm,m2,40,8000,320000\n"
        );
    }

    public function test_spreadsheet_rows_are_imported_immediately_on_upload(): void
    {
        Storage::fake(config('filesystems.default'));
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'organisation_id' => $user->organisation_id]);
        $this->entitle($user, 'boq.management');
        $imports = $this->entitle($user, 'boq.import.excel', ['boq_imports' => 5]);

        Livewire::actingAs($user)
            ->test(BoqsCreate::class)
            ->set('projectId', $project->id)
            ->set('file', $this->csv())
            ->call('save')
            ->assertHasNoErrors();

        $boq = Boq::where('project_id', $project->id)->sole();
        $this->assertSame(2, $boq->items()->count());
        $this->assertSame('under_review', $boq->status);
        $this->assertSame(1, (int) ($imports->fresh()->usage['boq_imports'] ?? 0));
        $this->assertStringContainsString('2 item(s) imported', session('status'));
    }

    public function test_upload_without_import_allowance_keeps_the_file_and_explains_why(): void
    {
        Storage::fake(config('filesystems.default'));
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'organisation_id' => $user->organisation_id]);
        $this->entitle($user, 'boq.management');

        Livewire::actingAs($user)
            ->test(BoqsCreate::class)
            ->set('projectId', $project->id)
            ->set('file', $this->csv())
            ->call('save');

        $boq = Boq::where('project_id', $project->id)->sole();
        $this->assertSame(0, $boq->items()->count());
        $this->assertSame('uploaded', $boq->status);
        $this->assertStringContainsString('no BOQ imports left', session('status'));
    }
}
