<?php

namespace App\Http\Requests;

use App\Models\Boq;
use Illuminate\Foundation\Http\FormRequest;

class ProcessBoqRequest extends FormRequest
{
    public function authorize(): bool
    {
        $boq = $this->route('boq');

        return $boq instanceof Boq && $this->user()?->can('process', $boq);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
