<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'rate' => $this->rate,
            'total' => $this->total,
            'boq_id' => $this->boq_id,
            'boq_item_id' => $this->boq_item_id,
        ];
    }
}
