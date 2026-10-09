<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExtractExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required_without:files', 'prohibits:files', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'files' => ['required_without:file', 'prohibits:file', 'array', 'min:1', 'max:10'],
            'files.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }
}
