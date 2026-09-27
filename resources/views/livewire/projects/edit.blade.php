<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Edit Project') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('Update project details') }}</p>
        </div>
        <a href="{{ url('/projects') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-lg transition">
            <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            {{ __('Back') }}
        </a>
    </div>

    <form wire:submit="save">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Project Name') }} <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="name" id="name" placeholder="{{ __('e.g. Riverside Office Block') }}" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="code" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Project Code') }}</label>
                    <input type="text" wire:model="code" id="code" placeholder="{{ __('e.g. KOD-2026-001') }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="client" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Client') }}</label>
                    <input type="text" wire:model="client" id="client" placeholder="{{ __('Client or project owner') }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @error('client') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="contractor" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Contractor') }}</label>
                    <input type="text" wire:model="contractor" id="contractor" placeholder="{{ __('Main contractor') }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @error('contractor') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="consultant" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Consultant') }}</label>
                    <input type="text" wire:model="consultant" id="consultant" placeholder="{{ __('Consulting firm or lead consultant') }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @error('consultant') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="quantitySurveyor" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Quantity Surveyor') }}</label>
                    <input type="text" wire:model="quantitySurveyor" id="quantitySurveyor" placeholder="{{ __('Quantity surveyor name or firm') }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @error('quantitySurveyor') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="projectManager" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Project Manager') }}</label>
                    <input type="text" wire:model="projectManager" id="projectManager" placeholder="{{ __('Project manager name') }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @error('projectManager') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="siteEngineer" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Site Engineer') }}</label>
                    <input type="text" wire:model="siteEngineer" id="siteEngineer" placeholder="{{ __('Site engineer name') }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @error('siteEngineer') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="fundingOrganisation" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Funding Organisation') }}</label>
                    <input type="text" wire:model="fundingOrganisation" id="fundingOrganisation" placeholder="{{ __('Funding organisation, if applicable') }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @error('fundingOrganisation') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="country" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Country') }}</label>
                    <input type="text" wire:model="country" id="country" list="country-options" autocomplete="country-name" placeholder="{{ __('e.g. Kenya') }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <datalist id="country-options">@foreach(\App\Models\Country::options() as $countryName)<option value="{{ $countryName }}">@endforeach</datalist>
                    @error('country') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="district" class="block text-sm font-medium text-gray-700 mb-1">{{ __('District') }}</label>
                    <input type="text" wire:model="district" id="district" placeholder="{{ __('e.g. district, county or state') }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @error('district') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="location" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Location') }}</label>
                    <input type="text" wire:model="location" id="location" placeholder="{{ __('Site, town or area') }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @error('location') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="projectType" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Project Type') }}</label>
                    <input type="text" wire:model="projectType" id="projectType" placeholder="{{ __('e.g. Commercial building, road or water works') }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @error('projectType') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="startDate" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Start Date') }}</label>
                    <input type="date" wire:model="startDate" id="startDate" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @error('startDate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="expectedCompletionDate" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Expected Completion Date') }}</label>
                    <input type="date" wire:model="expectedCompletionDate" id="expectedCompletionDate" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @error('expectedCompletionDate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="contractValue" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Contract Value') }}</label>
                    <input type="number" min="0" step="0.01" wire:model="contractValue" id="contractValue" placeholder="0.00" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @error('contractValue') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="currency" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Currency') }}</label>
                    <x-currency-select wire:model="currency" id="currency" :current="$currency" />
                    @error('currency') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Status') }}</label>
                    <select wire:model="status" id="status" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="draft">{{ __('Draft') }}</option>
                        <option value="active">{{ __('Active') }}</option>
                        <option value="completed">{{ __('Completed') }}</option>
                        <option value="archived">{{ __('Archived') }}</option>
                    </select>
                    @error('status') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Description') }}</label>
                    <textarea wire:model="description" id="description" rows="4" maxlength="5000" placeholder="{{ __('Add project scope, assumptions or other useful notes') }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"></textarea>
                    @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 mt-6 pt-6 border-t border-gray-200">
                <a href="{{ url('/projects') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-lg transition">{{ __('Cancel') }}</a>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold rounded-lg transition">{{ __('Update Project') }}</button>
            </div>
        </div>
    </form>
</div>
