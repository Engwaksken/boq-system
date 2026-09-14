<?php

namespace App\Http\Requests;

use App\Models\Boq;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class StoreBoqRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = Project::find($this->input('project_id'));

        return $project !== null
            && $this->user()?->can('create', [Boq::class, $project]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'exists:projects,id'],
            'file' => ['required', 'file', 'mimes:xlsx,csv,pdf,jpg,jpeg,png', 'max:20480'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
