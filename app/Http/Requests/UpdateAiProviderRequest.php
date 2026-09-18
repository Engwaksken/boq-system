<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiProviderRequest extends FormRequest
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
        $provider = $this->route('provider');

        return [
            'key' => ['sometimes', 'string', 'max:50', 'alpha_dash', Rule::unique('ai_providers', 'key')->ignore($provider->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'provider_type' => ['sometimes', 'string', Rule::in(['openai', 'gemini', 'google_gemini', 'ollama', 'anthropic', 'azure_openai'])],
            'api_base_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'default_model' => ['sometimes', 'string', 'max:100'],
            'api_key' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'organisation_id' => ['sometimes', 'nullable', 'exists:organisations,id'],
            'is_enabled' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'settings' => ['sometimes', 'nullable', 'array'],
            'settings.timeout' => ['sometimes', 'integer', 'min:5', 'max:300'],
            'settings.temperature' => ['sometimes', 'numeric', 'min:0', 'max:2'],
            'settings.headers' => ['sometimes', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.alpha_dash' => 'The key may only contain letters, numbers, dashes, and underscores.',
            'provider_type.in' => 'The provider type must be one of: openai, gemini, google_gemini, ollama, anthropic, azure_openai.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_enabled')) {
            $this->merge(['is_enabled' => $this->boolean('is_enabled')]);
        }
        if ($this->has('is_default')) {
            $this->merge(['is_default' => $this->boolean('is_default')]);
        }
        if ($this->has('sort_order')) {
            $this->merge(['sort_order' => $this->integer('sort_order')]);
        }
    }
}