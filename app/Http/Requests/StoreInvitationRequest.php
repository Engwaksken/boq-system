<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->whereIn('slug', [
                    'project-manager', 'procurement-officer', 'finance', 'user',
                ]),
            ],
            // Accepted for backwards compatibility, but the controller replaces it
            // with the server-defined six-hour lifetime.
            'expires_at' => ['sometimes', 'date'],
        ];
    }
}
