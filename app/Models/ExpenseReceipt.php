<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_id', 'uploaded_by_user_id', 'original_filename', 'mime_type',
        'file_size', 'storage_path', 'storage_disk',
    ];

    protected function casts(): array
    {
        return ['file_size' => 'integer'];
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
