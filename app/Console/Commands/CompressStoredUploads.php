<?php

namespace App\Console\Commands;

use App\Models\Boq;
use App\Models\CompanyProfile;
use App\Models\ExpenseReceipt;
use App\Models\User;
use App\Services\FileCompressor;
use Illuminate\Console\Command;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Compresses files uploaded before compression was added: BOQ photos/scans,
 * profile pictures, company logos and expense receipt photos. Leftover
 * estimated-prices files are removed. Run with --dry-run first to see the savings.
 */
class CompressStoredUploads extends Command
{
    protected $signature = 'uploads:compress {--dry-run : Report the savings without changing any file}';

    protected $description = 'Compress stored BOQ scans, profile pictures, logos and expense receipts, and remove leftover upload files';

    private int $before = 0;

    private int $after = 0;

    private int $files = 0;

    public function handle(FileCompressor $compressor): int
    {
        // The command object can be reused (e.g. by the scheduler): start from zero.
        $this->before = $this->after = $this->files = 0;
        $dry = (bool) $this->option('dry-run');
        $default = config('filesystems.default');

        Boq::withTrashed()->where('source_type', 'scan')->whereNotNull('source_file_path')->chunkById(100, function ($boqs) use ($compressor, $dry, $default) {
            foreach ($boqs as $boq) {
                $path = $this->compress($compressor, $default, $boq->source_file_path, FileCompressor::SCAN_MAX_SIDE, false, $dry);
                if ($path !== null && $path !== $boq->source_file_path) {
                    $boq->forceFill(['source_file_path' => $path])->saveQuietly();
                }
            }
        });

        User::whereNotNull('avatar_path')->chunkById(100, function ($users) use ($compressor, $dry) {
            foreach ($users as $user) {
                $path = $this->compress($compressor, 'public', $user->avatar_path, FileCompressor::AVATAR_MAX_SIDE, false, $dry);
                if ($path !== null && $path !== $user->avatar_path) {
                    $user->forceFill(['avatar_path' => $path])->saveQuietly();
                }
            }
        });

        CompanyProfile::whereNotNull('logo_path')->chunkById(100, function ($profiles) use ($compressor, $dry) {
            foreach ($profiles as $profile) {
                $path = $this->compress($compressor, 'public', $profile->logo_path, FileCompressor::LOGO_MAX_SIDE, true, $dry);
                if ($path !== null && $path !== $profile->logo_path) {
                    $profile->forceFill(['logo_path' => $path])->saveQuietly();
                }
            }
        });

        ExpenseReceipt::whereIn('mime_type', ['image/jpeg', 'image/png', 'image/webp'])
            ->whereNotNull('storage_path')
            ->chunkById(100, function ($receipts) use ($compressor, $dry) {
                foreach ($receipts as $receipt) {
                    $disk = $receipt->storage_disk ?: 'local';
                    $path = $this->compress($compressor, $disk, $receipt->storage_path, FileCompressor::SCAN_MAX_SIDE, false, $dry);
                    if ($path !== null && $path !== $receipt->storage_path) {
                        $receipt->forceFill([
                            'storage_path' => $path,
                            'mime_type' => $this->mimeFor($path),
                            'file_size' => Storage::disk($disk)->size($path),
                        ])->saveQuietly();
                    }
                }
            });

        // Estimated prices are copied onto BOQ items; their files are not needed.
        $leftovers = Storage::disk($default)->allFiles('boq-estimates');
        $leftoverBytes = array_sum(array_map(fn ($f) => Storage::disk($default)->size($f), $leftovers));
        if (! $dry && $leftovers !== []) {
            Storage::disk($default)->deleteDirectory('boq-estimates');
        }

        $saved = $this->before - $this->after + $leftoverBytes;
        $this->info(sprintf(
            '%s %d file(s) compressed and %d leftover file(s) removed: %s saved.',
            $dry ? '[dry run]' : 'Done:',
            $this->files,
            count($leftovers),
            $this->human($saved),
        ));

        return self::SUCCESS;
    }

    /** Returns the (possibly new) stored path, or null when the file was left as it is. */
    private function compress(FileCompressor $compressor, string $disk, string $path, int $maxSide, bool $keepTransparency, bool $dry): ?string
    {
        $storage = Storage::disk($disk);
        if (! $storage->exists($path)) {
            return null;
        }

        $absolute = $storage->path($path);
        $result = $compressor->image($absolute, $maxSide, $keepTransparency);
        if ($result === null) {
            return null;
        }

        try {
            $oldSize = filesize($absolute);
            $newSize = filesize($result['path']);
            if ($newSize >= $oldSize) {
                return null;
            }

            $this->before += $oldSize;
            $this->after += $newSize;
            $this->files++;

            if ($dry) {
                return null;
            }

            $directory = trim(dirname($path), '.');
            $newPath = $storage->putFileAs($directory, new File($result['path']), Str::beforeLast(basename($path), '.').'.'.$result['extension']);
            if ($newPath !== $path) {
                $storage->delete($path);
            }

            return $newPath;
        } finally {
            @unlink($result['path']);
        }
    }

    private function mimeFor(string $path): string
    {
        return match (strtolower((string) pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    private function human(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return round($value, 1).' '.$units[$i];
    }
}
