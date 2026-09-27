<div>
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-500">Languages available for BOQ translation, reports and user profiles.</p>
        <button type="button" wire:click="create" class="boq-btn-primary">
            <i class="fas fa-plus"></i> Add Language
        </button>
    </div>

    @if(session('language-message'))
        <div class="boq-flash mb-3"><i class="fas fa-circle-check"></i> {{ session('language-message') }}</div>
    @endif

    <x-bulk-bar :count="count($selected)" class="mb-3">
        <button type="button" wire:click="bulkDelete" wire:confirm="Delete the selected languages?" class="boq-btn-danger">
            <i class="fas fa-trash"></i> Delete
        </button>
    </x-bulk-bar>

    <div class="boq-table-wrapper rounded-lg border border-slate-200">
        <table class="boq-table">
            <thead>
                <tr>
                    <th class="boq-check-col"><x-select-all :ids="$languages->pluck('id')" :selected="$selected" /></th>
                    <th>Code</th>
                    <th>Language</th>
                    <th>Direction</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
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
                                <span class="boq-badge boq-badge-warning ml-1"><i class="fas fa-star"></i> Default</span>
                            @endif
                        </td>
                        <td>
                            <div class="boq-table-title">{{ $language->name }}</div>
                            <div class="boq-table-subtitle">{{ $language->native_name }}</div>
                        </td>
                        <td>{{ strtoupper($language->direction) }}</td>
                        <td>
                            <span class="boq-badge {{ $language->is_active ? 'boq-badge-success' : 'boq-badge-danger' }}">{{ $language->is_active ? 'Active' : 'Inactive' }}</span>
                        </td>
                        <td>
                            <div class="boq-table-actions justify-end">
                                <button type="button" wire:click="edit({{ $language->id }})" class="boq-icon-btn" title="Edit" aria-label="Edit"><i class="fas fa-pen"></i></button>
                                @unless($language->is_default)
                                    <button type="button" wire:click="setDefault({{ $language->id }})" class="boq-icon-btn" title="Make default" aria-label="Make default"><i class="far fa-star"></i></button>
                                    <button type="button" wire:click="toggleActive({{ $language->id }})" class="boq-icon-btn" title="{{ $language->is_active ? 'Deactivate' : 'Activate' }}" aria-label="{{ $language->is_active ? 'Deactivate' : 'Activate' }}"><i class="fas {{ $language->is_active ? 'fa-ban' : 'fa-circle-check' }}"></i></button>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="boq-table-empty">No languages yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($languages->hasPages())<div class="boq-pagination">{{ $languages->links() }}</div>@endif

    @if($showForm)
        <div class="boq-modal-backdrop" wire:key="language-modal" role="dialog" aria-modal="true" aria-labelledby="language-modal-title">
            <form wire:submit="save" class="boq-modal boq-modal-sm">
                <div class="boq-modal-head">
                    <h2 id="language-modal-title"><i class="fas fa-language"></i> {{ $editingId ? 'Edit Language' : 'Add Language' }}</h2>
                    <button type="button" wire:click="cancel" class="boq-modal-close" aria-label="Close"><i class="fas fa-xmark"></i></button>
                </div>

                <div class="boq-modal-body boq-form-grid">
                    <div>
                        <label for="lang-code" class="boq-field-label">ISO Code *</label>
                        <input id="lang-code" type="text" maxlength="10" wire:model="form.code" class="boq-field" placeholder="e.g. sw">
                        @error('form.code') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="lang-direction" class="boq-field-label">Text Direction *</label>
                        <select id="lang-direction" wire:model="form.direction" class="boq-field">
                            <option value="ltr">Left to right</option>
                            <option value="rtl">Right to left</option>
                        </select>
                        @error('form.direction') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="lang-name" class="boq-field-label">English Name *</label>
                        <input id="lang-name" type="text" wire:model="form.name" class="boq-field" placeholder="e.g. Swahili">
                        @error('form.name') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="lang-native" class="boq-field-label">Native Name *</label>
                        <input id="lang-native" type="text" wire:model="form.native_name" class="boq-field" placeholder="e.g. Kiswahili">
                        @error('form.native_name') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="lang-date" class="boq-field-label">Date Format *</label>
                        <input id="lang-date" type="text" wire:model="form.date_format" class="boq-field" placeholder="d/m/Y">
                        @error('form.date_format') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>
                    <label class="boq-check self-end">
                        <input type="checkbox" wire:model="form.is_active">
                        <span>Active</span>
                    </label>
                </div>

                <div class="boq-modal-foot">
                    <button type="button" wire:click="cancel" class="boq-btn-secondary">Cancel</button>
                    <button type="submit" class="boq-btn-primary"><i class="fas fa-floppy-disk"></i> Save</button>
                </div>
            </form>
        </div>
    @endif
</div>
