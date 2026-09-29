<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A selectable category managed in the database: project types and BOQ work
 * sections. Material categories are HardwareCategory records.
 */
class Category extends Model
{
    public const TYPE_PROJECT = 'project';

    public const TYPE_WORK = 'work';

    public const TYPES = [self::TYPE_PROJECT, self::TYPE_WORK];

    protected $fillable = ['type', 'name', 'slug', 'description', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Category $category): void {
            $category->name = trim($category->name);
            $category->slug = Str::slug($category->name);
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type)->orderBy('sort_order')->orderBy('name');
    }
}
