<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bill extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'facility_id',
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
     * The facility this bill belongs to.
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Elements in this bill.
     */
    public function elements(): HasMany
    {
        return $this->hasMany(Element::class);
    }

    /**
     * Items in this bill.
     */
    public function items(): HasMany
    {
        return $this->hasMany(BoqItem::class);
    }
}
