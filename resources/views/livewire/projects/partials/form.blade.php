{{--
    Shared project form (create + edit). Expects $submitLabel and $cancelUrl.
    Field ids, wire:model names and @error keys are the component's public properties.
--}}

<form wire:submit="save" class="flex flex-col gap-5" novalidate>

    @if($errors->any())
        <x-ui.alert type="error" :title="__('Please fix the highlighted fields.')">
            {{ trans_choice(':count field needs attention.|:count fields need attention.', $errors->count(), ['count' => $errors->count()]) }}
        </x-ui.alert>
    @endif

    <x-ui.card :title="__('Project details')" icon="fa-circle-info" :subtitle="__('Name the project and describe its scope.')">
        <div class="boq-form-grid">
            <x-ui.field :label="__('Project Name')" for="name" error="name" required class="boq-form-span-2">
                <input type="text" wire:model="name" id="name" placeholder="{{ __('e.g. Riverside Office Block') }}" required class="boq-field @error('name') has-error @enderror" @error('name') aria-invalid="true" @enderror>
            </x-ui.field>

            <x-ui.field :label="__('Project Code')" for="code" error="code">
                <input type="text" wire:model="code" id="code" placeholder="{{ __('e.g. KOD-2026-001') }}" class="boq-field @error('code') has-error @enderror">
            </x-ui.field>

            <x-ui.field :label="__('Status')" for="status" error="status">
                <select wire:model="status" id="status" class="boq-field @error('status') has-error @enderror">
                    <option value="draft">{{ __('Draft') }}</option>
                    <option value="active">{{ __('Active') }}</option>
                    <option value="completed">{{ __('Completed') }}</option>
                    <option value="archived">{{ __('Archived') }}</option>
                </select>
            </x-ui.field>

            <x-ui.field :label="__('Client')" for="client" error="client">
                <input type="text" wire:model="client" id="client" placeholder="{{ __('Client or project owner') }}" class="boq-field @error('client') has-error @enderror">
            </x-ui.field>

            <x-ui.field :label="__('Project Type')" for="projectType" error="projectType">
                {{-- Choose a project type from the database, or type another one. --}}
                <input type="text" wire:model="projectType" id="projectType" list="project-type-options" autocomplete="off" placeholder="{{ __('Choose or type a project type') }}" class="boq-field @error('projectType') has-error @enderror">
                <datalist id="project-type-options">
                    @foreach ($projectTypes ?? [] as $type)
                        <option value="{{ $type }}"></option>
                    @endforeach
                </datalist>
            </x-ui.field>

            <x-ui.field :label="__('Description')" for="description" error="description" class="boq-form-span-2">
                <textarea wire:model="description" id="description" rows="4" maxlength="5000" placeholder="{{ __('Add project scope, assumptions or other useful notes') }}" class="boq-field @error('description') has-error @enderror"></textarea>
            </x-ui.field>
        </div>
    </x-ui.card>

    <div class="grid gap-5 lg:grid-cols-2">
        <x-ui.card :title="__('Location')" icon="fa-location-dot">
            <div class="grid gap-4">
                <x-ui.field :label="__('Country')" for="country" error="country">
                    <input type="text" wire:model="country" id="country" list="country-options" autocomplete="country-name" placeholder="{{ __('e.g. Kenya') }}" class="boq-field @error('country') has-error @enderror">
                    <datalist id="country-options">@foreach(\App\Models\Country::options() as $countryName)<option value="{{ $countryName }}">@endforeach</datalist>
                </x-ui.field>

                <x-ui.field :label="__('District')" for="district" error="district">
                    <input type="text" wire:model="district" id="district" placeholder="{{ __('e.g. district, county or state') }}" class="boq-field @error('district') has-error @enderror">
                </x-ui.field>

                <x-ui.field :label="__('Location')" for="location" error="location" :hint="__('Used to match hardware prices near the site.')">
                    <input type="text" wire:model="location" id="location" placeholder="{{ __('Site, town or area') }}" class="boq-field @error('location') has-error @enderror">
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('Schedule & budget')" icon="fa-calendar-days">
            <div class="boq-form-grid">
                <x-ui.field :label="__('Start Date')" for="startDate" error="startDate">
                    <input type="date" wire:model="startDate" id="startDate" class="boq-field @error('startDate') has-error @enderror">
                </x-ui.field>

                <x-ui.field :label="__('Expected Completion Date')" for="expectedCompletionDate" error="expectedCompletionDate">
                    <input type="date" wire:model="expectedCompletionDate" id="expectedCompletionDate" class="boq-field @error('expectedCompletionDate') has-error @enderror">
                </x-ui.field>

                <x-ui.field :label="__('Contract Value')" for="contractValue" error="contractValue">
                    <input type="number" min="0" step="0.01" inputmode="decimal" wire:model="contractValue" id="contractValue" placeholder="0.00" class="boq-field @error('contractValue') has-error @enderror">
                </x-ui.field>

                <x-ui.field :label="__('Currency')" for="currency" error="currency">
                    <x-currency-select wire:model="currency" id="currency" :current="$currency" />
                </x-ui.field>
            </div>
        </x-ui.card>
    </div>

    <x-ui.card :title="__('Project team')" icon="fa-people-group" :subtitle="__('Optional. Shown on BOQ reports.')">
        <div class="boq-form-grid">
            <x-ui.field :label="__('Contractor')" for="contractor" error="contractor">
                <input type="text" wire:model="contractor" id="contractor" placeholder="{{ __('Main contractor') }}" class="boq-field @error('contractor') has-error @enderror">
            </x-ui.field>

            <x-ui.field :label="__('Consultant')" for="consultant" error="consultant">
                <input type="text" wire:model="consultant" id="consultant" placeholder="{{ __('Consulting firm or lead consultant') }}" class="boq-field @error('consultant') has-error @enderror">
            </x-ui.field>

            <x-ui.field :label="__('Quantity Surveyor')" for="quantitySurveyor" error="quantitySurveyor">
                <input type="text" wire:model="quantitySurveyor" id="quantitySurveyor" placeholder="{{ __('Quantity surveyor name or firm') }}" class="boq-field @error('quantitySurveyor') has-error @enderror">
            </x-ui.field>

            <x-ui.field :label="__('Project Manager')" for="projectManager" error="projectManager">
                <input type="text" wire:model="projectManager" id="projectManager" placeholder="{{ __('Project manager name') }}" class="boq-field @error('projectManager') has-error @enderror">
            </x-ui.field>

            <x-ui.field :label="__('Site Engineer')" for="siteEngineer" error="siteEngineer">
                <input type="text" wire:model="siteEngineer" id="siteEngineer" placeholder="{{ __('Site engineer name') }}" class="boq-field @error('siteEngineer') has-error @enderror">
            </x-ui.field>

            <x-ui.field :label="__('Funding Organisation')" for="fundingOrganisation" error="fundingOrganisation">
                <input type="text" wire:model="fundingOrganisation" id="fundingOrganisation" placeholder="{{ __('Funding organisation, if applicable') }}" class="boq-field @error('fundingOrganisation') has-error @enderror">
            </x-ui.field>
        </div>
    </x-ui.card>

    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-end">
        <x-ui.button variant="secondary" :href="$cancelUrl">{{ __('Cancel') }}</x-ui.button>
        <x-ui.button type="submit" icon="fa-floppy-disk" loading="save">{{ $submitLabel }}</x-ui.button>
    </div>
</form>
