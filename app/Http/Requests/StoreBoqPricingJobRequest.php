<?php

namespace App\Http\Requests;

use App\Models\Boq;
use App\Models\BoqPricingJob;
use Illuminate\Foundation\Http\FormRequest;

class StoreBoqPricingJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        $boq = $this->route('boq');

        return $boq instanceof Boq && $this->user()?->can('create', BoqPricingJob::class);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'location' => ['required', 'string', 'max:255'],
            'batch_size' => ['required', 'integer', 'in:10,20,25'],
        ];
    }

    public function messages(): array
    {
        return [
            'location.required' => 'Location is required for pricing.',
            'batch_size.required' => 'Batch size is required.',
            'batch_size.in' => 'Batch size must be one of: 10, 20, or 25.',
        ];
    }
}