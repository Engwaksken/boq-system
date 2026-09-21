<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'contact_name',
        'email',
        'phone',
        'location',
        'region',
        'country',
        'currency',
        'materials',
        'name_translations',
        'notes_translations',
        'notes',
        'rating',
        'preferred_language',
        'is_active',
        'metadata',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'materials' => 'array',
            'name_translations' => 'array',
            'notes_translations' => 'array',
            'rating' => 'decimal:2',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * Rates in the library supplied by this supplier.
     */
    public function rates(): HasMany
    {
        return $this->hasMany(Rate::class);
    }

    /**
     * Quotations received from this supplier.
     */
    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    /**
     * The user who created this supplier.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}