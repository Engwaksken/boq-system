<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvitationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return ['email' => ['sometimes', 'required', 'email', 'max:255'], 'role_id' => ['prohibited'], 'expires_at' => ['prohibited']];
    }
}
