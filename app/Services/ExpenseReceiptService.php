<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseReceipt;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ExpenseReceiptService
{
    public function store(User $user, Expense $expense, UploadedFile $file): ExpenseReceipt
    {
        Gate::forUser($user)->authorize('create', [ExpenseReceipt::class, $expense]);
        Validator::make(['file' => $file], ['file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:20480']])->validate();
        $disk = config('filesystems.disks.private') ? 'private' : 'local';
        $extension = strtolower($file->extension());
        $mime = $file->getMimeType();
        $allowed = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        abort_unless(isset($allowed[$extension]) && $allowed[$extension] === $mime, 422, 'Unsupported receipt file type.');

        $sha256 = hash_file('sha256', $file->getRealPath());
        abort_if($sha256 === false, 422, 'The receipt could not be read.');

        // Identical file content means the same receipt: refuse to attach it again
        // within the organisation rather than creating a second expense record.
        $duplicate = ExpenseReceipt::where('sha256', $sha256)
            ->whereHas('expense', fn ($query) => $query->where('organisation_id', $expense->organisation_id))
            ->exists();
        abort_if($duplicate, 409, 'This receipt has already been uploaded.');

        // Receipt photos are re-encoded to cut storage; the smaller of the original
        // and the compressed image is kept. PDFs are stored as uploaded.
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $path = app(FileCompressor::class)->storeImage(
                $file, 'expense-receipts', FileCompressor::SCAN_MAX_SIDE, false, $disk, (string) Str::uuid()
            );
            $mime = match (strtolower((string) pathinfo($path, PATHINFO_EXTENSION))) {
                'png' => 'image/png',
                'webp' => 'image/webp',
                default => 'image/jpeg',
            };
        } else {
            $path = $file->storeAs('expense-receipts', Str::uuid().'.'.$extension, $disk);
        }

        try {
            return $expense->receipts()->create([
                'uploaded_by_user_id' => $user->id, 'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $mime, 'file_size' => Storage::disk($disk)->size($path), 'storage_path' => $path, 'storage_disk' => $disk,
                'sha256' => $sha256,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }
    }
}
