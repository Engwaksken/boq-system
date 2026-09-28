@php
    $savedProfile = auth()->user()->companyProfile;
    $previewLogo = $companyLogo && ! $errors->has('companyLogo')
        ? $companyLogo->temporaryUrl()
        : ($removeCompanyLogo ? null : $savedProfile?->logoUrl());
    $countryNames = \App\Models\Country::options();
@endphp

<div class="grid gap-6 lg:grid-cols-5">
    <form wire:submit="saveCompanyProfile" class="boq-panel boq-panel-body lg:col-span-3">
        <h3 class="boq-section-title mb-1"><i class="fas fa-building"></i> {{ __('Company Profile') }}</h3>
        <p class="boq-section-subtitle mb-4">{{ __('Your business identity. It brands every BOQ you export and is watermarked on each PDF page.') }}</p>

        <div class="boq-form-grid">
            <div class="boq-form-span-2">
                <span class="boq-field-label">{{ __('Company Logo') }}</span>
                <div class="boq-upload-row">
                    @if($previewLogo)
                        <img src="{{ $previewLogo }}" alt="{{ __('Company Logo') }}" class="boq-upload-preview">
                    @else
                        <span class="boq-upload-preview is-empty">{{ __('No logo') }}</span>
                    @endif
                    <div class="flex flex-col gap-2">
                        <input id="company-logo" type="file" wire:model="companyLogo" accept="image/png,image/jpeg,image/webp" class="peer sr-only">
                        <label for="company-logo" class="boq-btn-secondary cursor-pointer peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand-600">
                            <i class="fas fa-upload" aria-hidden="true"></i> {{ __('Choose logo') }}
                        </label>
                        @if($previewLogo)
                            <button type="button" wire:click="$set('removeCompanyLogo', true)" class="boq-btn-secondary text-red-600"><i class="fas fa-trash"></i> {{ __('Remove') }}</button>
                        @endif
                    </div>
                </div>
                <p wire:loading wire:target="companyLogo" class="boq-field-help">{{ __('Uploading logo...') }}</p>
                @error('companyLogo') <p class="boq-field-error">{{ $message }}</p> @enderror
                <p class="boq-field-help">{{ __('PNG, JPG or WEBP up to 2 MB. A transparent PNG works best for the watermark.') }}</p>
            </div>

            @foreach([
                'company_name' => [__('Company Name'), 'text', __('e.g. Riverside Builders Ltd'), 2],
                'registration_number' => [__('Registration Number'), 'text', __('e.g. 80020001234567'), 1],
                'tin' => [__('TIN (if applicable)'), 'text', __('e.g. 1000123456'), 1],
                'city' => [__('District/City'), 'text', __('e.g. city, town or market'), 1],
                'physical_address' => [__('Physical Address'), 'text', __('e.g. Plot 1, Main Street'), 2],
                'postal_address' => [__('Postal Address'), 'text', __('e.g. P.O. Box 1234'), 1],
                'telephone' => [__('Telephone'), 'tel', '+1 202 555 0143', 1],
                'alt_telephone' => [__('Alternative Telephone'), 'tel', '+1 202 555 0144', 1],
                'email' => [__('Email'), 'email', 'info@example.com', 1],
                'website' => [__('Website'), 'url', 'https://example.com', 1],
            ] as $field => [$label, $type, $placeholder, $span])
                <div class="{{ $span === 2 ? 'boq-form-span-2' : '' }}">
                    <label for="company-{{ $field }}" class="boq-field-label">{{ $label }}@if($field === 'company_name') <span class="boq-field-required" aria-hidden="true">*</span>@endif</label>
                    <input id="company-{{ $field }}" type="{{ $type }}" wire:model.live.debounce.400ms="companyForm.{{ $field }}" @class(['boq-field', 'has-error' => $errors->has('companyForm.'.$field)]) placeholder="{{ $placeholder }}">
                    @error('companyForm.'.$field) <p class="boq-field-error">{{ $message }}</p> @enderror
                </div>

                @if($field === 'tin')
                    <div>
                        <label for="company-country" class="boq-field-label">{{ __('Country') }}</label>
                        <select id="company-country" wire:model.live="companyForm.country" class="boq-field">
                            <option value="">{{ __('Select...') }}</option>
                            @foreach($countryNames as $iso => $name)<option value="{{ $iso }}">{{ $name }}</option>@endforeach
                        </select>
                        @error('companyForm.country') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>
                @endif
            @endforeach

            <div class="boq-form-span-2">
                <label for="company-description" class="boq-field-label">{{ __('Company Description') }}</label>
                <textarea id="company-description" wire:model="companyForm.description" rows="3" class="boq-field boq-textarea" placeholder="{{ __('Add a short description of your business...') }}"></textarea>
                @error('companyForm.description') <p class="boq-field-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-5 flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="saveCompanyProfile,companyLogo" class="boq-btn-primary">
                <i wire:loading.remove wire:target="saveCompanyProfile" class="fas fa-floppy-disk"></i>
                <i wire:loading wire:target="saveCompanyProfile" class="fas fa-spinner fa-spin"></i>
                {{ __('Save Company Profile') }}
            </button>
        </div>
    </form>

    {{-- Live preview of the PDF letterhead --}}
    <aside class="lg:col-span-2">
        <div class="boq-panel boq-panel-body lg:sticky lg:top-20">
            <h3 class="boq-section-title mb-3"><i class="fas fa-eye"></i> {{ __('Preview') }}</h3>
            <div class="boq-company-preview">
                <div class="boq-company-preview-head">
                    @if($previewLogo)
                        <img src="{{ $previewLogo }}" alt="">
                    @endif
                    <div class="min-w-0">
                        <div class="boq-company-preview-name">{{ $companyForm['company_name'] ?: __('Your Company Name') }}</div>
                        <div class="boq-company-preview-lines">
                            @foreach(array_filter([
                                collect([$companyForm['physical_address'] ?? '', $companyForm['city'] ?? '', $countryNames[$companyForm['country'] ?? ''] ?? ''])->filter()->implode(', '),
                                $companyForm['postal_address'] ?? '',
                                collect([$companyForm['telephone'] ?? '', $companyForm['alt_telephone'] ?? ''])->filter()->implode(' / '),
                                collect([$companyForm['email'] ?? '', $companyForm['website'] ?? ''])->filter()->implode(' · '),
                            ]) as $line)
                                <div>{{ $line }}</div>
                            @endforeach
                        </div>
                    </div>
                    <div class="boq-company-preview-doc">{{ __('BILL OF QUANTITIES') }}</div>
                </div>
                <div class="boq-company-preview-body">
                    @if($previewLogo)
                        <img src="{{ $previewLogo }}" alt="" class="boq-company-preview-watermark">
                    @endif
                    <div class="boq-company-preview-rows"><span></span><span></span><span></span><span></span></div>
                </div>
            </div>
            <p class="boq-field-help mt-3">{{ __('BOQs keep the company details they were first exported with, even if you change this profile later.') }}</p>
        </div>
    </aside>
</div>
