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
            'purchase_date' => ['required', 'date'], 'supplier' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'], 'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['required', 'string', 'max:50'], 'rate' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'is_planned' => ['sometimes', 'boolean'],
            'explanation' => ['exclude_if:is_planned,true', 'required_if:is_planned,false', 'required_without:is_planned', 'nullable', 'string', 'max:10000'],
            'total' => ['exclude'], 'purchaser_user_id' => ['exclude'],
            'organisation_id' => ['exclude'], 'creator_user_id' => ['exclude'],
        ];
    }
}
