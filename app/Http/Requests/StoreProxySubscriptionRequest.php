<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProxySubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('subscriptions.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'exists:plans,id'],
            'beneficiary_id' => ['required', 'exists:users,id'],
            'payment_gateway_id' => ['required', 'exists:payment_gateways,id'],
            'payment_method' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan_id.required' => 'A plan must be selected.',
            'plan_id.exists' => 'The selected plan does not exist.',
            'beneficiary_id.required' => 'A beneficiary user must be selected.',
            'beneficiary_id.exists' => 'The selected beneficiary does not exist.',
            'payment_gateway_id.required' => 'A payment gateway must be selected.',
            'payment_gateway_id.exists' => 'The selected payment gateway does not exist.',
            'payment_method.string' => 'The payment method must be a string.',
            'payment_method.max' => 'The payment method may not be greater than 100 characters.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure payment_method is trimmed if provided
        if ($this->has('payment_method')) {
            $this->merge([
                'payment_method' => trim($this->input('payment_method')),
            ]);
        }
    }
}