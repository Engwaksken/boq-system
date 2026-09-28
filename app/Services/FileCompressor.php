<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Shrinks uploaded files before they are stored.
 *
 * - Photos and scans are resized to a sensible maximum and re-encoded as JPEG
 *   (PNG is kept when it has transparency, e.g. logos); the smaller of the
 *   original and the compressed file is kept.
 * - Gzip uploads (the mobile app gzips CSV/text files) are unpacked with a
 *   size limit so they can be read like any other upload.
 */
class FileCompressor
{
    /** Longest side for BOQ photos/scans: still sharp enough for text extraction. */
    public const SCAN_MAX_SIDE = 2400;

    public const AVATAR_MAX_SIDE = 512;

    public const LOGO_MAX_SIDE = 800;

    public const JPEG_QUALITY = 80;

    /** Largest size a gzip upload may unpack to. */
    public const MAX_UNPACKED_BYTES = 50 * 1024 * 1024;

    /**
     * Compressed copy of an image file as a temporary file, or null when it is
     * not an image GD can read or compressing would not make it smaller.
     *
     * @return array{path: string, extension: string}|null
     */
    public function image(string $path, int $maxSide, bool $keepTransparency = false): ?array
    {
        $info = @getimagesize($path);
        if ($info === false || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        [$width, $height, $type] = $info;
        if ($width < 1 || $height < 1 || $width * $height > 60_000_000) {
            return null; // not an image, or too large to decode safely
        }

        $source = @imagecreatefromstring((string) file_get_contents($path));
        if ($source === false) {
            return null;
        }

        $scale = min(1, $maxSide / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $hasAlpha = $keepTransparency && in_array($type, [IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true);

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        if ($hasAlpha) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        } else {
            // Flatten transparency onto white so text stays readable.
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        }
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($source);

        if ($type === IMAGETYPE_JPEG || ! $hasAlpha) {
            $this->orientJpeg($canvas, $path, $type);
        }

        // JPEG suits photos; PNG can be smaller for flat drawings and screenshots.
        // Keep whichever candidate is smallest, and only if it beats the original.
        $candidates = $hasAlpha ? ['png'] : ['jpg', 'png'];
        $original = filesize($path);
        $best = null;

        foreach ($candidates as $extension) {
            if ($extension === 'png' && ! $hasAlpha && $scale >= 1 && $type === IMAGETYPE_PNG) {
                continue; // same size and format as the original
            }
            $target = $this->temporaryFile($extension);
            $saved = $extension === 'png'
                ? imagepng($canvas, $target, 9)
                : imagejpeg($canvas, $target, self::JPEG_QUALITY);
            $size = $saved && is_file($target) ? filesize($target) : false;

            if ($size === false || $size >= $original || ($best !== null && $size >= $best['size'])) {
                @unlink($target);
                continue;
            }
            if ($best !== null) {
                @unlink($best['path']);
            }
            $best = ['path' => $target, 'extension' => $extension, 'size' => $size];
        }
        imagedestroy($canvas);

        return $best === null ? null : ['path' => $best['path'], 'extension' => $best['extension']];
    }

    /**
     * Stores an uploaded image compressed (or as-is when that is smaller) on a disk.
     */
    public function storeImage(UploadedFile $file, string $directory, int $maxSide, bool $keepTransparency = false, string $disk = 'public', ?string $basename = null): string
    {
        $compressed = $this->image($file->getRealPath(), $maxSide, $keepTransparency);
        $basename ??= Str::random(24);

        try {
            if ($compressed !== null) {
                return Storage::disk($disk)->putFileAs(
                    $directory,
                    new \Illuminate\Http\File($compressed['path']),
                    $basename.'.'.$compressed['extension'],
                );
            }

            return $file->storeAs($directory, $basename.'.'.($file->guessExtension() ?: 'jpg'), $disk);
        } finally {
            if ($compressed !== null) {
                @unlink($compressed['path']);
            }
        }
    }

    public function isGzip(string $path): bool
    {
        return (string) @file_get_contents($path, false, null, 0, 2) === "\x1F\x8B";
    }

    /**
     * Unpacks a gzip file into a temporary file.
     *
     * @throws ValidationException when it is damaged or unpacks to more than the limit
     */
    public function gunzip(string $path, string $attribute = 'file'): string
    {
        $input = @gzopen($path, 'rb');
        if ($input === false) {
            throw ValidationException::withMessages([$attribute => 'The compressed file could not be opened. Upload it again.']);
        }

        $target = $this->temporaryFile('bin');
        $output = fopen($target, 'wb');
        $written = 0;

        try {
            while (! gzeof($input)) {
                $chunk = gzread($input, 1024 * 1024);
                if ($chunk === false) {
                    throw ValidationException::withMessages([$attribute => 'The compressed file is damaged. Upload it again.']);
                }
                $written += strlen($chunk);
                if ($written > self::MAX_UNPACKED_BYTES) {
                    throw ValidationException::withMessages([$attribute => 'The file is too large once unpacked (50 MB maximum).']);
                }
                fwrite($output, $chunk);
            }
        } catch (ValidationException $e) {
            fclose($output);
            @unlink($target);
            throw $e;
        } finally {
            gzclose($input);
        }

        fclose($output);

        return $target;
    }

    /** Photos from phones are often stored sideways with an EXIF rotation flag. */
    private function orientJpeg(\GdImage &$canvas, string $path, int $type): void
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return;
        }

        $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle !== 0) {
            $rotated = imagerotate($canvas, $angle, 0);
            if ($rotated !== false) {
                imagedestroy($canvas);
                $canvas = $rotated;
            }
        }
    }

    private function temporaryFile(string $extension): string
    {
        $base = tempnam(sys_get_temp_dir(), 'cmp');
        @unlink($base);

        return $base.'.'.$extension;
    }
}
