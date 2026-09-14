<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'boq_id',
        'name',
        'name_translations',
        'description',
        'display_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name_translations' => 'array',
        ];
    }

    /**
     * The BOQ this facility belongs to.
     */
    public function boq(): BelongsTo
    {
        return $this->belongsTo(Boq::class);
    }

    /**
     * Bills in this facility.
     */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    /**
     * Items in this facility.
     */
    public function items(): HasMany
    {
        return $this->hasMany(BoqItem::class);
    }

    /**
     * Summaries for this facility.
     */
    public function summaries(): HasMany
    {
        return $this->hasMany(BoqSummary::class);
    }
}
