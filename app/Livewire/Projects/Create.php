<?php

namespace App\Livewire\Projects;

use App\Models\Project;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public string $name = '';

    public ?string $code = null;

    public ?string $client = null;

    public ?string $contractor = null;

    public ?string $consultant = null;

    public ?string $quantitySurveyor = null;

    public ?string $projectManager = null;

    public ?string $siteEngineer = null;

    public ?string $fundingOrganisation = null;

    public ?string $country = null;

    public ?string $district = null;

    public ?string $location = null;

    public ?string $projectType = null;

    public ?string $startDate = null;

    public ?string $expectedCompletionDate = null;

    public $contractValue = null;

    public string $currency = 'UGX';

    public string $status = 'draft';

    public ?string $description = null;

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255'],
            'client' => ['nullable', 'string', 'max:255'],
            'contractor' => ['nullable', 'string', 'max:255'],
            'consultant' => ['nullable', 'string', 'max:255'],
            'quantitySurveyor' => ['nullable', 'string', 'max:255'],
            'projectManager' => ['nullable', 'string', 'max:255'],
            'siteEngineer' => ['nullable', 'string', 'max:255'],
            'fundingOrganisation' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'projectType' => ['nullable', 'string', 'max:255'],
            'startDate' => ['nullable', 'date'],
            'expectedCompletionDate' => ['nullable', 'date', 'after_or_equal:startDate'],
            'contractValue' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'status' => ['required', 'string', 'in:draft,active,completed,archived'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $user = auth()->user();

        $project = Project::create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'client' => $validated['client'],
            'contractor' => $validated['contractor'],
            'consultant' => $validated['consultant'],
            'quantity_surveyor' => $validated['quantitySurveyor'],
            'project_manager' => $validated['projectManager'],
            'site_engineer' => $validated['siteEngineer'],
            'funding_organisation' => $validated['fundingOrganisation'],
            'country' => $validated['country'],
            'district' => $validated['district'],
            'location' => $validated['location'],
            'project_type' => $validated['projectType'],
            'start_date' => $validated['startDate'],
            'expected_completion_date' => $validated['expectedCompletionDate'],
            'contract_value' => $validated['contractValue'],
            'currency' => $validated['currency'],
            'status' => $validated['status'],
            'description' => $validated['description'],
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
        ]);

        session()->flash('status', 'Project created successfully.');

        $this->redirectRoute('projects.show', $project);
    }

    public function render()
    {
        return view('livewire.projects.create');
    }
}