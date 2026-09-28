<div
    class="max-w-3xl"
    x-data="{ saved: false }"
    x-on:display-preferences-saved.window="saved = true; setTimeout(() => saved = false, 2000)"
>
    <div class="boq-panel boq-panel-body">
        <div class="mb-4 flex items-start justify-between gap-3">
            <div>
                <h3 class="boq-section-title"><i class="fas fa-sliders" aria-hidden="true"></i> {{ __('Display & Regional Preferences') }}</h3>
                <p class="boq-section-subtitle">{{ __('How dates, numbers and money are shown to you. Changes save automatically.') }}</p>
            </div>
            <span x-show="saved" x-transition class="boq-badge boq-badge-success" role="status">
                <i class="fas fa-check"></i> {{ __('Saved') }}
            </span>
        </div>

        <div class="boq-form-grid">
            <div>
                <label for="pref-locale" class="boq-field-label">{{ __('Language') }}</label>
                <select id="pref-locale" wire:model.live="displayPrefs.locale" class="boq-field">
                    @foreach(\App\Models\Language::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get() as $language)
                        <option value="{{ $language->code }}">{{ $language->name }}{{ $language->native_name !== $language->name ? ' · '.$language->native_name : '' }}</option>
                    @endforeach
                </select>
                @error('displayPrefs.locale') <p class="boq-field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="pref-currency" class="boq-field-label">{{ __('Preferred Currency') }}</label>
                <x-currency-select id="pref-currency" wire:model.live="displayPrefs.currency" :current="$displayPrefs['currency'] ?? null" />
                @error('displayPrefs.currency') <p class="boq-field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="pref-date" class="boq-field-label">{{ __('Date Format') }}</label>
                <select id="pref-date" wire:model.live="displayPrefs.date_format" class="boq-field">
                    @foreach(\App\Models\User::DATE_FORMATS as $format => $label)
                        <option value="{{ $format }}">{{ $label }} · {{ now()->format($format) }}</option>
                    @endforeach
                </select>
                @error('displayPrefs.date_format') <p class="boq-field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="pref-number" class="boq-field-label">{{ __('Number Format') }}</label>
                <select id="pref-number" wire:model.live="displayPrefs.number_format" class="boq-field">
                    @foreach(\App\Models\User::NUMBER_FORMATS as $format)
                        <option value="{{ $format }}">{{ $format }}</option>
                    @endforeach
                </select>
                @error('displayPrefs.number_format') <p class="boq-field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="pref-per-page" class="boq-field-label">{{ __('Rows Per Page') }}</label>
                <select id="pref-per-page" wire:model.live="displayPrefs.per_page" class="boq-field">
                    @foreach([10, 20, 50, 100] as $size)
                        <option value="{{ $size }}">{{ $size }}</option>
                    @endforeach
                </select>
                @error('displayPrefs.per_page') <p class="boq-field-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>
</div>
