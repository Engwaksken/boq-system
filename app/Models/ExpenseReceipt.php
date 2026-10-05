<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class ExpenseReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_id', 'uploaded_by_user_id', 'original_filename', 'mime_type',
        'file_size', 'storage_path', 'storage_disk', 'sha256',
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

    public function scopeWithSha256(Builder $query, string $sha256): Builder
    {
        return $query->where('sha256', strtolower($sha256));
    }

    public function duplicates(): HasMany
    {
        return $this->hasMany(self::class, 'sha256', 'sha256')
            ->whereKeyNot($this->getKey());
    }
}
