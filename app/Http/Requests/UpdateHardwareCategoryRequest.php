<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHardwareCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('hardware-prices.manage') ?? false;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('hardware_categories', 'name')->ignore($category->id)],
            'description' => ['sometimes', 'nullable', 'string'],
            'default_items' => ['sometimes', 'nullable', 'array'],
            'default_items.*' => ['string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);
        }
        if ($this->has('sort_order')) {
            $this->merge(['sort_order' => $this->integer('sort_order')]);
        }
        if ($this->has('default_items')) {
            $this->merge(['default_items' => $this->input('default_items', [])]);
        }
    }
}