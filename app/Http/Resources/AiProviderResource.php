<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AiProvider */
class AiProviderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'provider_type' => $this->provider_type,
            'api_base_url' => $this->api_base_url,
            'default_model' => $this->default_model,
            'organisation_id' => $this->organisation_id,
            'is_enabled' => $this->is_enabled,
            'is_default' => $this->is_default,
            'sort_order' => $this->sort_order,
            'settings' => $this->settings,
            'last_tested_at' => $this->last_tested_at?->toISOString(),
            'last_test_status' => $this->last_test_status,
            'last_test_message' => $this->last_test_message,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
        ];
    }
}