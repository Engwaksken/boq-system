<?php

namespace Tests\Feature;

use App\Models\Boq;
use App\Models\Project;
use App\Models\User;
use App\Services\BoqSpreadsheetImporter;
use App\Services\BoqUploadNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use Tests\TestCase;

class BoqUploadNormalizerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.default'));
    }

    private function customer(): User
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(\App\Models\Role::where('slug', 'user')->value('id'));
        \App\Models\Entitlement::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'feature_id' => \App\Models\Feature::firstOrCreate(['code' => 'boq.management'], ['name' => 'BOQ management'])->id,
            'status' => 'active',
            'expires_at' => now()->addMonth(),
        ]);

        return $user;
    }

    private function normalize(UploadedFile $file): array
    {
        return app(BoqUploadNormalizer::class)->store($file, 'boqs/1');
    }

    private function contents(array $stored): string
    {
        return Storage::get($stored['path']);
    }

    public function test_semicolon_text_in_windows_1252_becomes_a_utf8_comma_csv(): void
    {
        $text = mb_convert_encoding("Description;Unit;Quantity;Rate\nCafé floor tiles;m2;10;1500\n", 'Windows-1252', 'UTF-8');

        $stored = $this->normalize(UploadedFile::fake()->createWithContent('boq.txt', $text));

        $this->assertSame('csv', $stored['extension']);
        $this->assertSame('excel', $stored['source_type']);
        $this->assertStringEndsWith('.csv', $stored['path']);
        $this->assertSame("Description,Unit,Quantity,Rate\n\"Café floor tiles\",m2,10,1500\n", $this->contents($stored));
    }

    public function test_tab_separated_utf16_text_from_excel_becomes_csv(): void
    {
        $text = "\xFF\xFE".mb_convert_encoding("Description\tUnit\tQuantity\tRate\nExcavation\tm3\t10\t1500\n", 'UTF-16LE', 'UTF-8');

        $stored = $this->normalize(UploadedFile::fake()->createWithContent('boq.tsv', $text));

        $this->assertSame("Description,Unit,Quantity,Rate\nExcavation,m3,10,1500\n", $this->contents($stored));
    }

    public function test_opendocument_spreadsheet_is_converted_to_xlsx_and_imports(): void
    {
        $ods = tempnam(sys_get_temp_dir(), 'ods').'.ods';
        $writer = new \OpenSpout\Writer\ODS\Writer();
        $writer->openToFile($ods);
        $writer->addRow(Row::fromValues(['Description', 'Unit', 'Quantity', 'Rate']));
        $writer->addRow(Row::fromValues(['Excavation', 'm3', 10, 1500]));
        $writer->addRow(Row::fromValues(['Blinding', 'm2', 5, 800]));
        $writer->close();

        $stored = $this->normalize(new UploadedFile($ods, 'boq.ods', null, null, true));
        @unlink($ods);

        $this->assertSame('xlsx', $stored['extension']);
        $this->assertStringStartsWith("PK\x03\x04", $this->contents($stored));

        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'organisation_id' => $user->organisation_id]);
        $boq = Boq::factory()->create([
            'project_id' => $project->id,
            'organisation_id' => $user->organisation_id,
            'source_type' => $stored['source_type'],
            'source_file_path' => $stored['path'],
        ]);

        $this->assertSame(2, app(BoqSpreadsheetImporter::class)->import($boq));
    }

    public function test_webp_photo_is_converted_to_jpeg(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD without WebP support.');
        }

        $image = imagecreatetruecolor(20, 20);
        ob_start();
        imagewebp($image);
        $webp = (string) ob_get_clean();

        $stored = $this->normalize(UploadedFile::fake()->createWithContent('scan.webp', $webp));

        $this->assertSame('jpg', $stored['extension']);
        $this->assertSame('scan', $stored['source_type']);
        $this->assertStringStartsWith("\xFF\xD8", $this->contents($stored));
    }

    public function test_pdf_is_kept_even_with_a_wrong_extension(): void
    {
        $stored = $this->normalize(UploadedFile::fake()->createWithContent('boq.txt', "%PDF-1.4\n%fake\n"));

        $this->assertSame('pdf', $stored['extension']);
        $this->assertSame('pdf', $stored['source_type']);
    }

    public function test_old_xls_and_word_files_get_clear_messages(): void
    {
        foreach ([
            ['old.xls', "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".str_repeat("\0", 512), '97-2003'],
            ['empty.csv', '', 'empty'],
        ] as [$name, $contents, $expected]) {
            try {
                $this->normalize(UploadedFile::fake()->createWithContent($name, $contents));
                $this->fail("Expected a validation error for {$name}.");
            } catch (ValidationException $e) {
                $this->assertStringContainsString($expected, $e->errors()['file'][0]);
            }
        }
    }

    public function test_api_upload_accepts_a_csv_the_server_guesses_as_text(): void
    {
        $user = $this->customer();
        $project = Project::factory()->create(['user_id' => $user->id, 'organisation_id' => $user->organisation_id]);

        $response = $this->actingAs($user, 'sanctum')->post('/api/v1/boqs', [
            'project_id' => $project->id,
            'file' => UploadedFile::fake()->createWithContent('My BOQ.CSV', "Description,Unit,Quantity,Rate\nExcavation,m3,10,1500\n"),
        ], ['Accept' => 'application/json']);

        $response->assertCreated();
        $boq = Boq::latest('id')->first();
        $this->assertSame('excel', $boq->source_type);
        $this->assertStringEndsWith('.csv', $boq->source_file_path);
        $this->assertSame('My BOQ', $boq->name);
    }

    public function test_api_upload_rejects_unsupported_extensions(): void
    {
        $user = $this->customer();
        $project = Project::factory()->create(['user_id' => $user->id, 'organisation_id' => $user->organisation_id]);

        $this->actingAs($user, 'sanctum')->post('/api/v1/boqs', [
            'project_id' => $project->id,
            'file' => UploadedFile::fake()->createWithContent('virus.exe', 'MZ'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');
    }
}
