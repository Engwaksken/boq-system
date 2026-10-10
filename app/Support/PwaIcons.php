<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * App icons made from the uploaded site logo (Admin > Settings), falling back
 * to the built-in icons only when no logo has been uploaded. Generated once per
 * logo and cached, and regenerated whenever the logo changes.
 */
class PwaIcons
{
    /** variant => [size, share of the canvas the logo may use, full-bleed background] */
    public const VARIANTS = [
        'icon-192' => [192, 0.80, false],
        'icon-512' => [512, 0.80, false],
        'maskable-512' => [512, 0.60, true],   // Android crops maskable icons to shapes
        'apple-touch-icon' => [180, 0.76, true],
    ];

    /** Bump to regenerate every cached icon after a drawing change. */
    private const DRAWING_VERSION = 1;

    /** Public URL of an icon, versioned so browsers pick up a new logo. */
    public static function url(string $variant): string
    {
        $source = self::source();

        if ($source === null) {
            return asset("icons/{$variant}.png");
        }

        return route('pwa.icon', ['variant' => $variant, 'v' => substr(self::fingerprint($source, $variant), 0, 10)]);
    }

    /** Absolute path of the PNG to send for a variant. */
    public static function path(string $variant): string
    {
        abort_unless(isset(self::VARIANTS[$variant]), 404);
        $default = public_path("icons/{$variant}.png");
        $source = self::source();

        if ($source === null) {
            return $default;
        }

        $directory = storage_path('app/pwa-icons');
        $target = $directory.'/'.self::fingerprint($source, $variant).'.png';

        if (is_file($target)) {
            return $target;
        }

        try {
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            self::draw($source, $target, ...self::VARIANTS[$variant]);

            return is_file($target) ? $target : $default;
        } catch (Throwable $exception) {
            report($exception);

            return $default;
        }
    }

    /** The uploaded site logo as a readable image file, or null. */
    public static function source(): ?string
    {
        $stored = trim((string) SiteSetting::get('logo', ''));
        if ($stored === '') {
            return null;
        }

        $path = Storage::disk('public')->path($stored);
        $info = is_file($path) ? @getimagesize($path) : false;

        if ($info !== false && in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
            return $path;
        }

        return null;
    }

    private static function fingerprint(string $source, string $variant): string
    {
        return sha1(implode('|', [$source, @filemtime($source), @filesize($source), $variant, self::DRAWING_VERSION]));
    }

    private static function draw(string $source, string $target, int $size, float $share, bool $fullBleed): void
    {
        $logo = @imagecreatefromstring((string) file_get_contents($source));
        if ($logo === false) {
            return;
        }
        imagepalettetotruecolor($logo);
        imagesavealpha($logo, true);

        $scale = 2; // draw at double size, then downsample for smooth edges
        $canvasSize = $size * $scale;
        $canvas = imagecreatetruecolor($canvasSize, $canvasSize);
        imagesavealpha($canvas, true);
        imagealphablending($canvas, false);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagealphablending($canvas, true);

        // Logos drawn on their own background keep it; transparent logos sit on white.
        [$red, $green, $blue] = self::backgroundColour($logo);
        $background = imagecolorallocate($canvas, $red, $green, $blue);

        if ($fullBleed) {
            imagefilledrectangle($canvas, 0, 0, $canvasSize, $canvasSize, $background);
        } else {
            self::roundedSquare($canvas, $canvasSize, (int) ($canvasSize * 0.2), $background);
        }

        $logoWidth = imagesx($logo);
        $logoHeight = imagesy($logo);
        $box = $canvasSize * $share;
        $fit = min($box / $logoWidth, $box / $logoHeight);
        $drawWidth = max(1, (int) round($logoWidth * $fit));
        $drawHeight = max(1, (int) round($logoHeight * $fit));
        imagecopyresampled(
            $canvas, $logo,
            (int) (($canvasSize - $drawWidth) / 2), (int) (($canvasSize - $drawHeight) / 2),
            0, 0, $drawWidth, $drawHeight, $logoWidth, $logoHeight,
        );
        imagedestroy($logo);

        $icon = imagecreatetruecolor($size, $size);
        imagesavealpha($icon, true);
        imagealphablending($icon, false);
        imagefill($icon, 0, 0, imagecolorallocatealpha($icon, 0, 0, 0, 127));
        imagecopyresampled($icon, $canvas, 0, 0, 0, 0, $size, $size, $canvasSize, $canvasSize);
        imagedestroy($canvas);

        imagepng($icon, $target, 9);
        imagedestroy($icon);
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function backgroundColour(\GdImage $logo): array
    {
        $corners = [[0, 0], [imagesx($logo) - 1, 0], [0, imagesy($logo) - 1], [imagesx($logo) - 1, imagesy($logo) - 1]];
        $colours = [];

        foreach ($corners as [$x, $y]) {
            $rgba = imagecolorat($logo, $x, $y);
            $alpha = ($rgba >> 24) & 0x7F;
            if ($alpha > 20) {
                return [255, 255, 255]; // transparent corner: white background
            }
            $colours[] = [($rgba >> 16) & 0xFF, ($rgba >> 8) & 0xFF, $rgba & 0xFF];
        }

        // Opaque logo: use its (average) corner colour so the icon looks seamless.
        return array_map(fn (int $channel) => (int) round(array_sum(array_column($colours, $channel)) / count($colours)), [0, 1, 2]);
    }

    private static function roundedSquare(\GdImage $image, int $size, int $radius, int $colour): void
    {
        imagefilledrectangle($image, $radius, 0, $size - $radius - 1, $size - 1, $colour);
        imagefilledrectangle($image, 0, $radius, $size - 1, $size - $radius - 1, $colour);
        foreach ([[$radius, $radius], [$size - $radius - 1, $radius], [$radius, $size - $radius - 1], [$size - $radius - 1, $size - $radius - 1]] as [$x, $y]) {
            imagefilledellipse($image, $x, $y, $radius * 2, $radius * 2, $colour);
        }
    }
}
