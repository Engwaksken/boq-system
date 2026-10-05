<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'organisation_id', 'project_id', 'creator_user_id', 'purchaser_user_id',
        'boq_id', 'boq_item_id',
        'purchase_date', 'supplier', 'description', 'quantity', 'unit', 'rate',
        'total', 'currency', 'payment_method', 'is_planned', 'explanation',
        'deduplication_hash',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'quantity' => 'decimal:3',
            'rate' => 'decimal:2',
            'total' => 'decimal:2',
            'is_planned' => 'boolean',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where('organisation_id', $user->organisation_id)
            ->where(fn ($participants) => $participants->where('creator_user_id', $user->id)->orWhere('purchaser_user_id', $user->id))
            ->whereHas('project', fn ($projects) => $projects->where('organisation_id', $user->organisation_id)
                ->whereHas('assignments', fn ($assignments) => $assignments->where('user_id', $user->id)->whereNull('deleted_at')));
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function boq(): BelongsTo
    {
        return $this->belongsTo(Boq::class);
    }

    public function boqItem(): BelongsTo
    {
        return $this->belongsTo(BoqItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }

    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchaser_user_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(ExpenseReceipt::class);
    }

}
