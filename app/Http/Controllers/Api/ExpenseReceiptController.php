<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpenseReceiptRequest;
use App\Http\Resources\ExpenseReceiptResource;
use App\Models\Expense;
use App\Models\ExpenseReceipt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExpenseReceiptController extends Controller
{
    public function store(StoreExpenseReceiptRequest $request, Expense $expense)
    {
        $this->authorize('create', [ExpenseReceipt::class, $expense]);
        $file = $request->file('file');
        $disk = config('filesystems.disks.private') ? 'private' : 'local';
        $extension = strtolower($file->extension());
        $mime = $file->getMimeType();
        $allowed = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        abort_unless(isset($allowed[$extension]) && $allowed[$extension] === $mime, 422, 'Unsupported receipt file type.');
        $path = $file->storeAs('expense-receipts', Str::uuid().'.'.$extension, $disk);
        try {
        $receipt = $expense->receipts()->create([
            'uploaded_by_user_id' => $request->user()->id, 'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $mime, 'file_size' => $file->getSize(), 'storage_path' => $path, 'storage_disk' => $disk,
        ]);
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }
        return (new ExpenseReceiptResource($receipt))->response()->setStatusCode(201);
    }

    public function download(ExpenseReceipt $receipt)
    {
        $this->authorize('view', $receipt);
        $disk = $receipt->storage_disk ?: (config('filesystems.disks.private') ? 'private' : 'local');
        abort_unless(in_array($disk, ['private', 'local'], true), 404);
        abort_unless(Storage::disk($disk)->exists($receipt->storage_path), 404);
        abort_unless(str_starts_with($receipt->storage_path, 'expense-receipts/'), 404);
        $filename = str_replace(["\r", "\n", '"'], '', basename($receipt->original_filename));
        return Storage::disk($disk)->download($receipt->storage_path, $filename, ['Content-Type' => $receipt->mime_type, 'X-Content-Type-Options' => 'nosniff', 'Content-Disposition' => 'attachment; filename="'.$filename.'"']);
    }
}
