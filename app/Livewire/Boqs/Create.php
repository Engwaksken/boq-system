<?php

namespace App\Livewire\Boqs;

use App\Models\Boq;
use App\Models\Project;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Create extends Component
{
    use WithFileUploads;

    public $projectId = null;

    public ?string $name = null;

    public $file = null;

    public $projects;

    public function mount(): void
    {
        $user = auth()->user();

        $this->projects = Project::query()
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id);

                if ($user->organisation_id !== null) {
                    $query->orWhere('organisation_id', $user->organisation_id);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    public function save(): void
    {
        $validated = $this->validate([
            'projectId' => ['required', 'exists:projects,id'],
            'file' => ['required', 'file', 'mimes:xlsx,csv,pdf,jpg,jpeg,png', 'max:20480'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = auth()->user();
        $project = Project::findOrFail($validated['projectId']);

        $tenantAccess = $user->organisation_id !== null
            && $project->organisation_id === $user->organisation_id;
        $personalAccess = $project->user_id === $user->id
            && $project->organisation_id === null
            && $user->organisation_id === null;

        abort_unless($tenantAccess || $personalAccess, 403);

        $path = $this->file->store("boqs/{$project->id}");
        $extension = strtolower($this->file->getClientOriginalExtension());

        $boq = Boq::create([
            'project_id' => $project->id,
            'organisation_id' => $project->organisation_id,
            'name' => ($validated['name'] ?? null) ?: pathinfo($this->file->getClientOriginalName(), PATHINFO_FILENAME),
            'currency' => $project->currency ?: 'UGX',
            'status' => 'uploaded',
            'source_type' => in_array($extension, ['xlsx', 'csv']) ? 'excel' : ($extension === 'pdf' ? 'pdf' : 'scan'),
            'source_file_path' => $path,
        ]);

        session()->flash('status', 'BOQ uploaded successfully.');

        $this->redirectRoute('boqs.show', $boq);
    }

    public function render()
    {
        return view('livewire.boqs.create');
    }
}
