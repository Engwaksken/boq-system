<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'purchase_date' => ['sometimes', 'required', 'date'], 'supplier' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string', 'max:10000'], 'quantity' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'unit' => ['sometimes', 'required', 'string', 'max:50'], 'rate' => ['sometimes', 'required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'required', 'string', 'size:3'],
            'payment_method' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_planned' => ['sometimes', 'boolean'],
            'explanation' => ['exclude_if:is_planned,true', 'required_if:is_planned,false', 'sometimes', 'nullable', 'string', 'max:10000'],
            'purchaser_user_id' => ['prohibited'], 'total' => ['prohibited'],
            'project_id' => ['prohibited'], 'organisation_id' => ['prohibited'],
        ];
    }
}
