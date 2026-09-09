<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Element extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'bill_id',
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
     * The bill this element belongs to.
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /**
     * Sub-elements in this element.
     */
    public function subElements(): HasMany
    {
        return $this->hasMany(SubElement::class);
    }

    /**
     * Items in this element.
     */
    public function items(): HasMany
    {
        return $this->hasMany(BoqItem::class);
    }
}
