<div>
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-500">{{ __('Currencies offered in price, plan, project and supplier forms.') }}</p>
        <button type="button" wire:click="create" class="boq-btn-primary">
            <i class="fas fa-plus"></i> {{ __('Add Currency') }}
        </button>
        <div class="flex flex-wrap gap-2"><x-ui.export-buttons /></div>
    </div>

    @if(session('currency-message'))
        <x-ui.alert type="success" class="mb-3" dismissible>{{ session('currency-message') }}</x-ui.alert>
    @endif

    <x-bulk-bar :count="count($selected)" class="mb-3">
        <button type="button" wire:click="bulkDelete" wire:confirm="{{ __('Delete the selected currencies? Existing records keep their currency code.') }}" class="boq-btn-danger">
            <i class="fas fa-trash"></i> {{ __('Delete') }}
        </button>
    </x-bulk-bar>

    <div class="boq-table-wrapper rounded-lg border border-slate-200">
        <table class="boq-table">
            <thead>
                <tr>
                    <th class="boq-check-col"><x-select-all :ids="$currencies->pluck('id')" :selected="$selected" /></th>
                    <th>{{ __('Code') }}</th>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Symbol') }}</th>
                    <th>{{ __('Decimals') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($currencies as $currency)
                    <tr wire:key="currency-{{ $currency->id }}">
                        <td class="boq-check-col">
                            @unless($currency->is_default)
                                <x-select-row :id="$currency->id" />
                            @endunless
                        </td>
                        <td class="font-mono font-semibold">
                            {{ $currency->code }}
                            @if($currency->is_default)
                                <span class="boq-badge boq-badge-warning ml-1"><i class="fas fa-star"></i> {{ __('Default') }}</span>
                            @endif
                        </td>
                        <td>{{ $currency->name }}</td>
                        <td>{{ $currency->symbol ?: '—' }}</td>
                        <td>{{ $currency->decimal_places }}</td>
                        <td>
                            <span class="boq-badge {{ $currency->is_active ? 'boq-badge-success' : 'boq-badge-danger' }}">{{ $currency->is_active ? __('Active') : __('Inactive') }}</span>
                        </td>
                        <td>
                            <div class="boq-table-actions justify-end">
                                <button type="button" wire:click="edit({{ $currency->id }})" class="boq-icon-btn" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}"><i class="fas fa-pen"></i></button>
                                @unless($currency->is_default)
                                    <button type="button" wire:click="setDefault({{ $currency->id }})" class="boq-icon-btn" title="{{ __('Make default') }}" aria-label="{{ __('Make default') }}"><i class="far fa-star"></i></button>
                                    <button type="button" wire:click="toggleActive({{ $currency->id }})" class="boq-icon-btn" title="{{ $currency->is_active ? __('Deactivate') : __('Activate') }}" aria-label="{{ $currency->is_active ? __('Deactivate') : __('Activate') }}"><i class="fas {{ $currency->is_active ? 'fa-ban' : 'fa-circle-check' }}"></i></button>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="boq-empty-table">{{ __('No currencies yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($currencies->hasPages())<div class="boq-pagination">{{ $currencies->links() }}</div>@endif

    @if($showForm)
        <div class="boq-modal-backdrop" wire:key="currency-modal" x-data x-trap.noscroll="true" @keydown.escape.window="$wire.cancel()" role="dialog" aria-modal="true" aria-labelledby="currency-modal-title">
            <form wire:submit="save" class="boq-modal boq-modal-sm">
                <div class="boq-modal-head">
                    <h2 id="currency-modal-title"><i class="fas fa-coins"></i> {{ $editingId ? __('Edit Currency') : __('Add Currency') }}</h2>
                    <button type="button" wire:click="cancel" class="boq-modal-close" aria-label="{{ __('Close') }}"><i class="fas fa-xmark"></i></button>
                </div>

                <div class="boq-modal-body boq-form-grid">
                    <div>
                        <label for="cur-code" class="boq-field-label">{{ __('ISO Code *') }}</label>
                        <input id="cur-code" type="text" maxlength="3" wire:model="form.code" class="boq-field uppercase" placeholder="{{ __('e.g. KES') }}">
                        @error('form.code') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="cur-symbol" class="boq-field-label">{{ __('Symbol') }}</label>
                        <input id="cur-symbol" type="text" wire:model="form.symbol" class="boq-field" placeholder="{{ __('e.g. KSh') }}">
                        @error('form.symbol') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="boq-form-span-2">
                        <label for="cur-name" class="boq-field-label">{{ __('Name *') }}</label>
                        <input id="cur-name" type="text" wire:model="form.name" class="boq-field" placeholder="{{ __('e.g. Kenyan Shilling') }}">
                        @error('form.name') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="cur-decimals" class="boq-field-label">{{ __('Decimal Places *') }}</label>
                        <input placeholder="e.g. 2" id="cur-decimals" type="number" min="0" max="4" wire:model="form.decimal_places" class="boq-field">
                        @error('form.decimal_places') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="cur-order" class="boq-field-label">{{ __('Sort Order *') }}</label>
                        <input placeholder="e.g. 10" id="cur-order" type="number" min="0" wire:model="form.sort_order" class="boq-field">
                        @error('form.sort_order') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>
                    <label class="boq-check boq-form-span-2">
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
</div>
