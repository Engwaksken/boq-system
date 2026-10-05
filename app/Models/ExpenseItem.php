<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseItem extends Model
{
    protected $fillable = ['expense_id', 'boq_id', 'boq_item_id', 'description', 'quantity', 'unit', 'rate', 'total'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'rate' => 'decimal:2', 'total' => 'decimal:2'];
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function boq(): BelongsTo
    {
        return $this->belongsTo(Boq::class);
    }

    public function boqItem(): BelongsTo
    {
        return $this->belongsTo(BoqItem::class);
    }
}
