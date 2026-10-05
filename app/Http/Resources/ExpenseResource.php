<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'boq_id' => $this->boq_id,
            'boq_item_id' => $this->boq_item_id,
            'purchase_date' => $this->purchase_date?->toDateString(),
            'supplier' => $this->supplier,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'rate' => $this->rate,
            'total' => $this->total,
            'currency' => $this->currency,
            'payment_method' => $this->payment_method,
            'is_planned' => $this->is_planned,
            'explanation' => $this->explanation,
            'receipts' => ExpenseReceiptResource::collection($this->whenLoaded('receipts')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
