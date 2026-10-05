<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'boq_id' => ['nullable', 'integer', 'exists:boqs,id'],
            'boq_item_id' => ['nullable', 'integer', 'exists:boq_items,id'],
            'purchase_date' => ['required', 'date'], 'supplier' => ['nullable', 'string', 'max:255'],
            'items' => ['sometimes', 'array', 'min:1', 'max:100'],
            'items.*.description' => ['required_with:items', 'string', 'max:10000'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.unit' => ['required_with:items', 'string', 'max:50'],
            'items.*.rate' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.boq_id' => ['nullable', 'integer', 'exists:boqs,id'],
            'items.*.boq_item_id' => ['nullable', 'integer', 'exists:boq_items,id'],
            'description' => ['required_without:items', 'nullable', 'string', 'max:10000'],
            'quantity' => ['required_without:items', 'nullable', 'numeric', 'gt:0'],
            'unit' => ['required_without:items', 'nullable', 'string', 'max:50'],
            'rate' => ['required_without:items', 'nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'is_planned' => ['sometimes', 'boolean'],
            'explanation' => ['exclude_if:is_planned,true', 'required_if:is_planned,false', 'required_without:is_planned', 'nullable', 'string', 'max:10000'],
            'total' => ['exclude'], 'purchaser_user_id' => ['exclude'],
            'organisation_id' => ['exclude'], 'creator_user_id' => ['exclude'],
        ];
    }
}
