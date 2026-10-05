<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'original_filename' => $this->original_filename, 'mime_type' => $this->mime_type, 'file_size' => $this->file_size, 'created_at' => $this->created_at, 'download_url' => url('/api/v1/expense-receipts/'.$this->id.'/download')];
    }
}
