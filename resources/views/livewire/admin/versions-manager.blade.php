<div class="boq-page-stack" x-data="{ confirmDelete: false, deleteId: null, deleteName: '' }">
    <x-ui.page-header
        :title="__('Product Versions')"
        icon="fa-box-open"
        :subtitle="__('Manage app releases and the features each version introduces.')"
    >
        <x-slot:actions>
            <x-ui.button icon="fa-plus" wire:click="create">{{ __('Add Version') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.flash :keys="['message', 'status', 'error']" />

    <div class="boq-panel">
        <div class="boq-toolbar border-b border-slate-200">
            <div class="boq-input-icon-wrap boq-toolbar-grow">
                <i class="fas fa-magnifying-glass boq-input-icon" aria-hidden="true"></i>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search versions...') }}" aria-label="{{ __('Search versions...') }}" class="boq-field boq-field-with-icon">
            </div>
        </div>

        <x-bulk-bar :count="count($selected)">
            <button type="button" wire:click="bulkSetActive(true)" class="boq-btn-secondary"><i class="fas fa-circle-check" aria-hidden="true"></i> {{ __('Activate') }}</button>
            <button type="button" wire:click="bulkSetActive(false)" class="boq-btn-secondary"><i class="fas fa-ban" aria-hidden="true"></i> {{ __('Deactivate') }}</button>
            <button type="button" wire:click="bulkDelete" wire:confirm="{{ __('Delete the selected versions? Versions in use are deactivated instead.') }}" class="boq-btn-danger"><i class="fas fa-trash" aria-hidden="true"></i> {{ __('Delete') }}</button>
        </x-bulk-bar>

        <x-ui.table>
            <thead>
                <tr>
                    <th class="boq-check-col"><x-select-all :ids="$versions->pluck('id')" :selected="$selected" /></th>
                    <th>{{ __('Version') }}</th>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Released') }}</th>
                    <th>{{ __('Classification') }}</th>
                    <th>{{ __('New Features') }}</th>
                    <th>{{ __('Top-up') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($versions as $version)
                    <tr wire:key="version-{{ $version->id }}">
                        <td class="boq-check-col"><x-select-row :id="$version->id" /></td>
                        <td><span class="boq-version-badge"><i class="fas fa-code-branch" aria-hidden="true"></i> v{{ $version->version_number }}</span></td>
                        <td>
                            <div class="boq-table-title">{{ $version->name }}</div>
                            <div class="boq-table-subtitle">{{ \Illuminate\Support\Str::limit((string) $version->release_notes, 70) }}</div>
                        </td>
                        <td class="whitespace-nowrap">{{ \App\Support\Format::date($version->release_date) ?? '—' }}</td>
                        <td>
                            <x-ui.badge :color="$version->classification === 'major' ? 'warning' : ($version->classification === 'minor' ? 'info' : 'neutral')">{{ __(ucfirst((string) $version->classification)) }}</x-ui.badge>
                        </td>
                        <td class="text-xs text-slate-600">{{ count($version->included_features ?? []) ? implode(', ', array_slice($version->included_features, 0, 3)).(count($version->included_features) > 3 ? '…' : '') : '—' }}</td>
                        <td>{{ $version->requires_topup ? __('Top-up') : __('Included') }}</td>
                        <td><x-ui.status :status="$version->is_active ? 'active' : 'inactive'" /></td>
                        <td class="text-right">
                            <div class="boq-table-actions">
                                <button type="button" wire:click="edit({{ $version->id }})" class="boq-icon-btn" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}"><i class="fas fa-pen" aria-hidden="true"></i></button>
                                <button type="button" wire:click="toggleActive({{ $version->id }})" class="boq-icon-btn" title="{{ $version->is_active ? __('Deactivate') : __('Activate') }}" aria-label="{{ $version->is_active ? __('Deactivate') : __('Activate') }}"><i class="fas {{ $version->is_active ? 'fa-ban' : 'fa-circle-check' }}" aria-hidden="true"></i></button>
                                <button type="button" @click="deleteId={{ $version->id }}; deleteName=@js($version->name); confirmDelete=true" class="boq-icon-btn boq-icon-danger" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}"><i class="fas fa-trash" aria-hidden="true"></i></button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="p-0"><x-ui.empty-state icon="fa-box-open" :title="__('No product versions found.')" /></td></tr>
                @endforelse
            </tbody>
        </x-ui.table>

        @if($versions->hasPages())<div class="boq-pagination">{{ $versions->links() }}</div>@endif
    </div>

    @if($showForm)
        <x-ui.modal
            wire:key="version-form-modal"
            id="version-form"
            :title="$editingId ? __('Edit Version') : __('Create Version')"
            :subtitle="__('Changes are saved without leaving this page.')"
            icon="fa-box-open"
            size="lg"
            close="cancel"
            submit="save"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <x-ui.field :label="__('Version number')" for="version-number" error="form.version_number" required>
                    <input id="version-number" wire:model="form.version_number" class="boq-field @error('form.version_number') has-error @enderror" placeholder="2.0">
                </x-ui.field>
                <x-ui.field :label="__('Name')" for="version-name" error="form.name" required>
                    <input id="version-name" placeholder="{{ __('Enter name') }}" wire:model="form.name" class="boq-field @error('form.name') has-error @enderror">
                </x-ui.field>
                <x-ui.field :label="__('Classification')" for="version-classification" error="form.classification">
                    <select id="version-classification" wire:model="form.classification" class="boq-field">
                        <option value="major">{{ __('Major') }}</option>
                        <option value="minor">{{ __('Minor') }}</option>
                        <option value="patch">{{ __('Patch') }}</option>
                    </select>
                </x-ui.field>
                <x-ui.field :label="__('Release date')" for="version-date" error="form.release_date">
                    <input id="version-date" wire:model="form.release_date" type="date" class="boq-field @error('form.release_date') has-error @enderror">
                </x-ui.field>
                <x-ui.field :label="__('Minimum supported version')" for="version-min" error="form.minimum_supported_version">
                    <input id="version-min" wire:model="form.minimum_supported_version" class="boq-field" placeholder="1.0">
                </x-ui.field>
                <x-ui.field :label="__('Requires top-up')" for="version-topup" error="form.requires_topup">
                    <select id="version-topup" wire:model="form.requires_topup" class="boq-field">
                        <option value="1">{{ __('Yes') }}</option>
                        <option value="0">{{ __('No') }}</option>
                    </select>
                </x-ui.field>

                <x-ui.field :label="__('Release notes')" for="version-notes" error="form.release_notes" class="md:col-span-3">
                    <textarea id="version-notes" placeholder="{{ __('Add release notes...') }}" wire:model="form.release_notes" rows="3" class="boq-field"></textarea>
                </x-ui.field>
                <x-ui.field :label="__('Included features')" for="version-features" error="form.included_features" :hint="__('Comma separated feature codes.')" class="md:col-span-3">
                    <input id="version-features" wire:model="form.included_features" class="boq-field" placeholder="variations,cost.tracking">
                </x-ui.field>
                <x-ui.field :label="__('Eligible plans')" for="version-plans" error="form.eligible_plans" :hint="__('Comma separated plan codes; leave blank for all plans.')" class="md:col-span-3">
                    <input id="version-plans" wire:model="form.eligible_plans" class="boq-field" placeholder="monthly-professional,one-time">
                </x-ui.field>

                <label class="boq-check md:col-span-3"><input type="checkbox" wire:model="form.is_active"> {{ __('Active') }}</label>
            </div>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="cancel">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" icon="fa-floppy-disk" loading="save">{{ __('Save Version') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    <div x-show="confirmDelete" x-cloak class="boq-modal-backdrop" role="dialog" aria-modal="true" @keydown.escape.window="confirmDelete=false">
        <div class="boq-modal boq-modal-sm" @click.outside="confirmDelete=false">
            <div class="boq-modal-head">
                <h2><i class="fas fa-trash" aria-hidden="true"></i> {{ __('Delete version?') }}</h2>
                <button type="button" @click="confirmDelete=false" class="boq-modal-close" aria-label="{{ __('Close') }}"><i class="fas fa-xmark" aria-hidden="true"></i></button>
            </div>
            <div class="boq-modal-body boq-modal-message">{{ __('Delete') }} <strong x-text="deleteName"></strong>{{ __('? Versions still referenced by active subscriptions are deactivated instead.') }}</div>
            <div class="boq-modal-foot">
                <button type="button" @click="confirmDelete=false" class="boq-btn-secondary">{{ __('Cancel') }}</button>
                <button type="button" @click="$wire.destroy(deleteId); confirmDelete=false" class="boq-btn-danger">{{ __('Delete') }}</button>
            </div>
        </div>
    </div>
</div>
