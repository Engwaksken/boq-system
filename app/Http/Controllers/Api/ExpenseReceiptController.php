<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpenseReceiptRequest;
use App\Http\Resources\ExpenseReceiptResource;
use App\Models\Expense;
use App\Models\ExpenseReceipt;
use Illuminate\Support\Facades\Storage;

class ExpenseReceiptController extends Controller
{
    public function store(StoreExpenseReceiptRequest $request, Expense $expense)
    {
        $receipt = app(\App\Services\ExpenseReceiptService::class)->store($request->user(), $expense, $request->file('file'));
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
