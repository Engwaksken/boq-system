<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseReceiptRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:20480']]; }
}
