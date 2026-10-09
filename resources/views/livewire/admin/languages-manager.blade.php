<div>
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-500">{{ __('Languages available for BOQ translation, reports and user profiles.') }}</p>
        <button type="button" wire:click="create" class="boq-btn-primary">
            <i class="fas fa-plus"></i> {{ __('Add Language') }}
        </button>
        <div class="flex flex-wrap gap-2"><x-ui.export-buttons /></div>
    </div>

    @if(session('language-message'))
        <x-ui.alert type="success" class="mb-3" dismissible>{{ session('language-message') }}</x-ui.alert>
    @endif

    <x-bulk-bar :count="count($selected)" class="mb-3">
        <button type="button" wire:click="bulkDelete" wire:confirm="{{ __('Delete the selected languages?') }}" class="boq-btn-danger">
            <i class="fas fa-trash"></i> {{ __('Delete') }}
        </button>
    </x-bulk-bar>

    <div class="boq-table-wrapper rounded-lg border border-slate-200">
        <table class="boq-table">
            <thead>
                <tr>
                    <th class="boq-check-col"><x-select-all :ids="$languages->pluck('id')" :selected="$selected" /></th>
                    <th>{{ __('Code') }}</th>
                    <th>{{ __('Language') }}</th>
                    <th>{{ __('Direction') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($languages as $language)
                    <tr wire:key="language-{{ $language->id }}">
                        <td class="boq-check-col">
                            @unless($language->is_default)
                                <x-select-row :id="$language->id" />
                            @endunless
                        </td>
                        <td class="font-mono font-semibold">
                            {{ $language->code }}
                            @if($language->is_default)
                                <span class="boq-badge boq-badge-warning ml-1"><i class="fas fa-star"></i> {{ __('Default') }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="boq-table-title">{{ $language->name }}</div>
                            <div class="boq-table-subtitle">{{ $language->native_name }}</div>
                        </td>
                        <td>{{ strtoupper($language->direction) }}</td>
                        <td>
                            <span class="boq-badge {{ $language->is_active ? 'boq-badge-success' : 'boq-badge-danger' }}">{{ $language->is_active ? __('Active') : __('Inactive') }}</span>
                        </td>
                        <td>
                            <div class="boq-table-actions justify-end">
                                <button type="button" wire:click="openTranslations({{ $language->id }})" class="boq-icon-btn" title="{{ __('Translate') }}" aria-label="{{ __('Translate') }}"><i class="fas fa-language"></i></button>
                                <button type="button" wire:click="edit({{ $language->id }})" class="boq-icon-btn" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}"><i class="fas fa-pen"></i></button>
                                @unless($language->is_default)
                                    <button type="button" wire:click="setDefault({{ $language->id }})" class="boq-icon-btn" title="{{ __('Make default') }}" aria-label="{{ __('Make default') }}"><i class="far fa-star"></i></button>
                                    <button type="button" wire:click="toggleActive({{ $language->id }})" class="boq-icon-btn" title="{{ $language->is_active ? __('Deactivate') : __('Activate') }}" aria-label="{{ $language->is_active ? __('Deactivate') : __('Activate') }}"><i class="fas {{ $language->is_active ? 'fa-ban' : 'fa-circle-check' }}"></i></button>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="boq-empty-table">{{ __('No languages yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($languages->hasPages())<div class="boq-pagination">{{ $languages->links() }}</div>@endif

    @if($showForm)
        <div class="boq-modal-backdrop" wire:key="language-modal" x-data x-trap.noscroll="true" @keydown.escape.window="$wire.cancel()" role="dialog" aria-modal="true" aria-labelledby="language-modal-title">
            <form wire:submit="save" class="boq-modal boq-modal-sm">
                <div class="boq-modal-head">
                    <h2 id="language-modal-title"><i class="fas fa-language"></i> {{ $editingId ? __('Edit Language') : __('Add Language') }}</h2>
                    <button type="button" wire:click="cancel" class="boq-modal-close" aria-label="{{ __('Close') }}"><i class="fas fa-xmark"></i></button>
                </div>

                <div class="boq-modal-body boq-form-grid">
                    <div>
                        <label for="lang-code" class="boq-field-label">{{ __('ISO Code *') }}</label>
                        <input id="lang-code" type="text" maxlength="10" wire:model="form.code" class="boq-field" placeholder="{{ __('e.g. sw') }}">
                        @error('form.code') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="lang-direction" class="boq-field-label">{{ __('Text Direction *') }}</label>
                        <select id="lang-direction" wire:model="form.direction" class="boq-field">
                            <option value="ltr">{{ __('Left to right') }}</option>
                            <option value="rtl">{{ __('Right to left') }}</option>
                        </select>
                        @error('form.direction') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="lang-name" class="boq-field-label">{{ __('English Name *') }}</label>
                        <input id="lang-name" type="text" wire:model="form.name" class="boq-field" placeholder="{{ __('e.g. Swahili') }}">
                        @error('form.name') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="lang-native" class="boq-field-label">{{ __('Native Name *') }}</label>
                        <input id="lang-native" type="text" wire:model="form.native_name" class="boq-field" placeholder="{{ __('e.g. Kiswahili') }}">
                        @error('form.native_name') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="lang-date" class="boq-field-label">{{ __('Date Format *') }}</label>
                        <input id="lang-date" type="text" wire:model="form.date_format" class="boq-field" placeholder="d/m/Y">
                        @error('form.date_format') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>
                    <label class="boq-check self-end">
                        <input type="checkbox" wire:model="form.is_active">
                        <span>{{ __('Active') }}</span>
                    </label>
                </div>

                <div class="boq-modal-foot">
                    <button type="button" wire:click="cancel" class="boq-btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="boq-btn-primary"><i class="fas fa-floppy-disk"></i> {{ __('Save') }}</button>
                </div>
            </form>
        </div>
    @endif

    @if($translating)
        <div class="boq-modal-backdrop" wire:key="translations-modal" x-data x-trap.noscroll="true" @keydown.escape.window="$wire.closeTranslations()" role="dialog" aria-modal="true" aria-labelledby="translations-title">
            <form wire:submit="saveTranslations" class="boq-modal boq-modal-xl">
                <div class="boq-modal-head">
                    <h2 id="translations-title"><i class="fas fa-language"></i> {{ __('Translate') }} · {{ strtoupper($translating) }}</h2>
                    <button type="button" wire:click="closeTranslations" class="boq-modal-close" aria-label="{{ __('Close') }}"><i class="fas fa-xmark"></i></button>
                </div>

                <div class="boq-modal-body space-y-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <input type="search" wire:model.live.debounce.300ms="translationSearch" class="boq-field md:max-w-sm" placeholder="{{ __('Search text or translation...') }}">
                        <label class="boq-check">
                            <input type="checkbox" wire:model.live="untranslatedOnly">
                            <span>{{ __('Untranslated only') }}</span>
                        </label>
                    </div>
                    <p class="boq-field-help">{{ __('Leave a field blank to fall back to English. Changes apply immediately after saving and survive deployments.') }}</p>

                    <div class="boq-table-wrapper rounded-lg border border-slate-200">
                        <table class="boq-table">
                            <thead>
                                <tr>
                                    <th class="w-1/2">{{ __('English') }}</th>
                                    <th>{{ __('Translation') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($translationRows as $row)
                                    <tr wire:key="tr-{{ $row['hash'] }}">
                                        <td class="text-sm text-slate-700">{{ $row['key'] }}</td>
                                        <td>
                                            <input type="text" wire:model="drafts.{{ $row['hash'] }}" class="boq-field" placeholder="{{ $row['key'] }}" aria-label="{{ __('Translation for') }} {{ $row['key'] }}">
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="boq-table-empty">{{ __('Nothing matches this search.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($translationPages > 1)
                        <div class="flex items-center justify-between text-sm text-slate-500">
                            <button type="button" wire:click="translationPageTo({{ $translationPage - 1 }})" class="boq-btn-secondary" @disabled($translationPage <= 1)><i class="fas fa-chevron-left"></i></button>
                            <span>{{ __('Page') }} {{ $translationPage }} / {{ $translationPages }}</span>
                            <button type="button" wire:click="translationPageTo({{ $translationPage + 1 }})" class="boq-btn-secondary" @disabled($translationPage >= $translationPages)><i class="fas fa-chevron-right"></i></button>
                        </div>
                    @endif
                </div>

                <div class="boq-modal-foot">
                    <button type="button" wire:click="closeTranslations" class="boq-btn-secondary">{{ __('Close') }}</button>
                    <button type="submit" class="boq-btn-primary"><i class="fas fa-floppy-disk"></i> {{ __('Save this page') }}</button>
                </div>
            </form>
        </div>
    @endif
</div>
