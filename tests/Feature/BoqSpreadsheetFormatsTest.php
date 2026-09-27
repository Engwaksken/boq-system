<?php

namespace Tests\Feature;

use App\Models\Boq;
use App\Models\Project;
use App\Models\User;
use App\Services\BoqSpreadsheetImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BoqSpreadsheetFormatsTest extends TestCase
{
    use RefreshDatabase;

    private function boqWithFile(string $filename, string $contents): Boq
    {
        Storage::fake(config('filesystems.default'));
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'organisation_id' => $user->organisation_id]);
        $path = "boqs/{$project->id}/{$filename}";
        Storage::disk(config('filesystems.default'))->put($path, $contents);

        return Boq::factory()->create([
            'project_id' => $project->id,
            'organisation_id' => $user->organisation_id,
            'source_type' => 'excel',
            'source_file_path' => $path,
        ]);
    }

    public function test_csv_stored_with_a_txt_extension_is_imported(): void
    {
        $boq = $this->boqWithFile('guessed.txt', "Description,Unit,Quantity,Rate\nExcavation,m3,10,1500\nBlinding,m2,5,800\n");

        $this->assertSame(2, app(BoqSpreadsheetImporter::class)->import($boq));
    }

    public function test_semicolon_separated_csv_is_imported(): void
    {
        $boq = $this->boqWithFile('european.csv', "Description;Unit;Quantity;Rate\nExcavation;m3;10;1500\n");

        $this->assertSame(1, app(BoqSpreadsheetImporter::class)->import($boq));
    }

    public function test_old_xls_files_get_a_clear_message(): void
    {
        $boq = $this->boqWithFile('old.xlsx', "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".str_repeat("\0", 512));

        try {
            app(BoqSpreadsheetImporter::class)->import($boq);
            $this->fail('Expected a validation error.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('97-2003', $e->errors()['boq'][0]);
        }
    }
}
