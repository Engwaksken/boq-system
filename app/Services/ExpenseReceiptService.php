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
        $path = $file->storeAs('expense-receipts', Str::uuid().'.'.$extension, $disk);
        try {
            return $expense->receipts()->create([
                'uploaded_by_user_id' => $user->id, 'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $mime, 'file_size' => $file->getSize(), 'storage_path' => $path, 'storage_disk' => $disk,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }
    }
}
