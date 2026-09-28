<?php

namespace Tests\Feature;

use App\Models\Boq;
use App\Models\Project;
use App\Models\User;
use App\Services\BoqUploadNormalizer;
use App\Services\FileCompressor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UploadCompressionTest extends TestCase
{
    use RefreshDatabase;

    /** A wide PNG of per-pixel noise (like a detailed photo): JPEG is far smaller. */
    private function bigPng(int $width = 2600, int $height = 400): string
    {
        $image = imagecreatetruecolor($width, $height);
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                imagesetpixel($image, $x, $y, random_int(0, 0xFFFFFF));
            }
        }
        ob_start();
        imagepng($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    public function test_flat_images_that_are_already_small_are_kept(): void
    {
        $image = imagecreatetruecolor(800, 600);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagefilledrectangle($image, 10, 10, 200, 100, imagecolorallocate($image, 0, 0, 0));
        $path = tempnam(sys_get_temp_dir(), 'flat').'.png';
        imagepng($image, $path, 9);
        imagedestroy($image);

        $this->assertNull(app(FileCompressor::class)->image($path, FileCompressor::SCAN_MAX_SIDE));
        @unlink($path);
    }

    public function test_boq_scans_are_resized_and_stored_as_jpeg(): void
    {
        Storage::fake(config('filesystems.default'));
        $png = $this->bigPng();

        $stored = app(BoqUploadNormalizer::class)->store(UploadedFile::fake()->createWithContent('scan.png', $png), 'boqs/1');

        $this->assertSame('jpg', $stored['extension']);
        $this->assertSame('scan', $stored['source_type']);
        $contents = Storage::get($stored['path']);
        $this->assertLessThan(strlen($png), strlen($contents));
        [$width, $height] = getimagesizefromstring($contents);
        $this->assertSame(FileCompressor::SCAN_MAX_SIDE, max($width, $height));
    }

    public function test_gzipped_csv_uploads_are_unpacked_and_imported(): void
    {
        Storage::fake(config('filesystems.default'));
        $csv = "Description,Unit,Quantity,Rate\n".str_repeat("Excavation,m3,10,1500\n", 200);

        $stored = app(BoqUploadNormalizer::class)->store(
            UploadedFile::fake()->createWithContent('boq.csv.gz', gzencode($csv, 9)),
            'boqs/1',
        );

        $this->assertSame('csv', $stored['extension']);
        $this->assertSame($csv, Storage::get($stored['path']));
    }

    public function test_damaged_gzip_gives_a_clear_message(): void
    {
        Storage::fake(config('filesystems.default'));

        $this->expectException(ValidationException::class);
        app(BoqUploadNormalizer::class)->store(UploadedFile::fake()->createWithContent('boq.csv.gz', "\x1F\x8B\x08\x00broken"), 'boqs/1');
    }

    public function test_avatars_are_resized(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->post('/api/v1/auth/avatar', ['avatar' => UploadedFile::fake()->image('me.png', 2000, 1500)], ['Accept' => 'application/json'])
            ->assertOk();

        [$width] = getimagesizefromstring(Storage::disk('public')->get($user->fresh()->avatar_path));
        $this->assertSame(FileCompressor::AVATAR_MAX_SIDE, $width);
    }

    public function test_command_compresses_existing_scans_and_removes_leftovers(): void
    {
        Storage::fake(config('filesystems.default'));
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'organisation_id' => $user->organisation_id]);
        Storage::put('boqs/1/old.png', $png = $this->bigPng());
        Storage::put('boq-estimates/1/leftover.csv', 'ID,Estimated Rate');
        $boq = Boq::factory()->create([
            'project_id' => $project->id,
            'organisation_id' => $user->organisation_id,
            'source_type' => 'scan',
            'source_file_path' => 'boqs/1/old.png',
        ]);

        $this->artisan('uploads:compress', ['--dry-run' => true])->expectsOutputToContain('[dry run] 1 file(s) compressed')->assertSuccessful();
        Storage::assertExists('boqs/1/old.png');

        $this->artisan('uploads:compress')->expectsOutputToContain('1 file(s) compressed')->assertSuccessful();

        $boq->refresh();
        $this->assertSame('boqs/1/old.jpg', $boq->source_file_path);
        Storage::assertMissing('boqs/1/old.png');
        $this->assertLessThan(strlen($png), Storage::size('boqs/1/old.jpg'));
        Storage::assertMissing('boq-estimates/1/leftover.csv');
    }

    public function test_user_role_is_given_the_price_permission(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $role = DB::table('roles')->where('slug', 'user')->value('id');
        $permission = DB::table('permissions')->where('slug', 'hardware-prices.view')->value('id');
        DB::table('permission_role')->where('role_id', $role)->where('permission_id', $permission)->delete();

        (require database_path('migrations/2026_09_28_000005_ensure_user_role_can_view_prices.php'))->up();

        $this->assertTrue(DB::table('permission_role')->where('role_id', $role)->where('permission_id', $permission)->exists());
    }
}
