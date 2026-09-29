<?php

namespace Tests\Feature\Concerns;

use App\Models\Boq;
use App\Models\Entitlement;
use App\Models\Feature;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;

/**
 * Helpers for signature tests: a customer with BOQ access, a BOQ they own and
 * signature images like the drawing pad (transparent PNG) or a photo (JPEG on
 * white paper) produces.
 */
trait CreatesSignatures
{
    protected function customer(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', 'user')->value('id'));

        Entitlement::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'feature_id' => Feature::firstOrCreate(['code' => 'boq.management'], ['name' => 'BOQ management'])->id,
            'status' => 'active',
            'expires_at' => now()->addMonth(),
        ]);

        return $user;
    }

    protected function boqFor(User $user, array $attributes = []): Boq
    {
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'name' => 'Kampala Clinic',
            'client' => 'Ministry of Health',
        ]);

        return Boq::factory()->create(array_merge([
            'project_id' => $project->id,
            'organisation_id' => $user->organisation_id,
            'owner_id' => $user->id,
            'name' => 'Clinic Block A',
        ], $attributes));
    }

    /** A signature-like scribble; transparent PNG, or on white when $paper. */
    protected function signaturePng(bool $paper = false, int $width = 600, int $height = 200): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, $paper
            ? imagecolorallocate($image, 250, 250, 248)
            : imagecolorallocatealpha($image, 255, 255, 255, 127));
        imagealphablending($image, true);

        $ink = imagecolorallocate($image, 20, 30, 60);
        imagesetthickness($image, 4);
        $previous = null;
        for ($x = 60; $x <= $width - 60; $x += 4) {
            $y = (int) ($height / 2 + sin($x / 22) * ($height / 4) * cos($x / 90));
            if ($previous) {
                imageline($image, $previous[0], $previous[1], $x, $y, $ink);
            }
            $previous = [$x, $y];
        }
        imageline($image, 80, (int) ($height * 0.78), $width - 90, (int) ($height * 0.72), $ink);

        ob_start();
        imagepng($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    protected function signatureDataUrl(): string
    {
        return 'data:image/png;base64,'.base64_encode($this->signaturePng());
    }

    protected function signatureJpeg(): string
    {
        $image = imagecreatefromstring($this->signaturePng(true));
        ob_start();
        imagejpeg($image, null, 90);
        imagedestroy($image);

        return (string) ob_get_clean();
    }
}
