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
            'file' => \App\Services\BoqUploadNormalizer::rules(),
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

        // Detect the real format and convert it to a standard one (.xlsx, .csv, .pdf, .jpg/.png).
        $stored = app(\App\Services\BoqUploadNormalizer::class)->store($this->file, "boqs/{$project->id}");

        $boq = Boq::create([
            'project_id' => $project->id,
            'organisation_id' => $project->organisation_id,
            'name' => ($validated['name'] ?? null) ?: pathinfo($this->file->getClientOriginalName(), PATHINFO_FILENAME),
            'currency' => $project->currency ?: \App\Support\Regional::currency(),
            'status' => 'uploaded',
            'source_type' => $stored['source_type'],
            'source_file_path' => $stored['path'],
        ]);

        session()->flash('status', $this->importNow($boq, $user));

        $this->redirectRoute('boqs.show', $boq);
    }

    /**
     * Excel/CSV rows are imported immediately (no AI needed), using one BOQ import
     * from the plan, the same as the API's process endpoint. PDFs and scans are
     * extracted later with "Generate BOQ".
     */
    private function importNow(Boq $boq, $user): string
    {
        if ($boq->source_type !== 'excel') {
            return 'BOQ uploaded. Press "Generate BOQ" to extract the items from this document.';
        }

        $gate = app(\App\Services\EntitlementGate::class);
        $allowance = $gate->find($user, 'boq.import.excel', 'boq_imports');

        if (! $user->isSuperAdmin() && ! $allowance) {
            return 'BOQ uploaded, but your plan has no BOQ imports left. Upgrade or buy a top-up, then press "Generate BOQ".';
        }

        try {
            $count = app(\App\Services\BoqSpreadsheetImporter::class)->import($boq);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return 'BOQ uploaded, but the items could not be read: '.collect($e->errors())->flatten()->first();
        } catch (\Throwable $e) {
            report($e);

            return 'BOQ uploaded, but the items could not be read. Check the file layout and try "Generate BOQ".';
        }

        if ($count === 0) {
            return 'BOQ uploaded, but no item rows were found. Check that the sheet has Description, Unit, Quantity and Rate columns.';
        }

        $boq->update(['status' => 'under_review']);

        if ($allowance) {
            $gate->consume($allowance, 'boq_imports');
        }

        return "BOQ uploaded and {$count} item(s) imported. Press \"Generate BOQ\" to price them.";
    }

    public function render()
    {
        return view('livewire.boqs.create');
    }
}
