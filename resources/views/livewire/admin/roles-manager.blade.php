<div class="boq-page-stack" x-data="{ confirmDelete: false, deleteId: null, deleteName: '' }">
    <x-ui.page-header
        :title="__('Roles & Permissions')"
        icon="fa-user-shield"
        :subtitle="__('Control access to BOQ, pricing, reports and administration.')"
    >
        <x-slot:actions>
            <x-ui.button icon="fa-plus" wire:click="create">{{ __('Add Role') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.flash :keys="['message', 'status', 'error']" />

    <div class="boq-panel">
        <x-ui.table>
            <thead>
                <tr>
                    <th>{{ __('Role') }}</th>
                    <th>{{ __('Users') }}</th>
                    <th>{{ __('Permissions') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th class="text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($roles as $role)
                    <tr wire:key="role-{{ $role->id }}">
                        <td>
                            <div class="boq-table-title">{{ $role->name }}</div>
                            <div class="boq-table-subtitle"><span class="boq-code">{{ $role->slug }}</span></div>
                        </td>
                        <td>{{ \App\Support\Format::number($role->users_count, 0) }}</td>
                        <td class="text-slate-600">{{ trans_choice(':count permission|:count permissions', $role->permissions->count(), ['count' => $role->permissions->count()]) }}</td>
                        <td>
                            @if($role->is_system)
                                <x-ui.badge icon="fa-lock">{{ __('System') }}</x-ui.badge>
                            @else
                                <x-ui.badge color="brand">{{ __('Custom') }}</x-ui.badge>
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="boq-table-actions">
                                <button type="button" wire:click="edit({{ $role->id }})" class="boq-icon-btn" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}"><i class="fas fa-pen" aria-hidden="true"></i></button>
                                @unless($role->is_system)
                                    <button type="button" @click="deleteId={{ $role->id }}; deleteName=@js($role->name); confirmDelete=true" class="boq-icon-btn boq-icon-danger" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}"><i class="fas fa-trash" aria-hidden="true"></i></button>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-0"><x-ui.empty-state icon="fa-user-shield" :title="__('No roles yet.')" /></td></tr>
                @endforelse
            </tbody>
        </x-ui.table>
    </div>

    @if($showForm)
        @php $editingSystem = $editingId && \App\Models\Role::whereKey($editingId)->value('is_system'); @endphp

        <x-ui.modal
            wire:key="role-form-modal"
            id="role-form"
            :title="$editingId ? __('Edit Role') : __('Create Role')"
            :subtitle="__('Configure permissions in this modal.')"
            icon="fa-user-shield"
            size="lg"
            close="cancel"
            submit="save"
        >
            <div class="boq-form-grid">
                <x-ui.field :label="__('Name')" for="role-name" error="name" required>
                    <input id="role-name" placeholder="{{ __('Enter name') }}" wire:model="name" class="boq-field @error('name') has-error @enderror">
                </x-ui.field>

                <x-ui.field :label="__('Slug')" for="role-slug" error="slug" :hint="$editingSystem ? __('System role slugs cannot be changed.') : null">
                    <input id="role-slug" wire:model="slug" class="boq-field @error('slug') has-error @enderror" placeholder="{{ __('auto if empty') }}" @disabled($editingSystem)>
                </x-ui.field>

                <x-ui.field :label="__('Description')" for="role-description" error="description" class="boq-form-span-2">
                    <textarea id="role-description" placeholder="{{ __('Add description...') }}" wire:model="description" rows="2" class="boq-field"></textarea>
                </x-ui.field>

                <fieldset class="boq-form-span-2">
                    <legend class="boq-field-label">{{ __('Permissions') }}</legend>
                    <div class="grid gap-3 md:grid-cols-3">
                        @foreach($permissions as $module => $items)
                            <div class="rounded-lg border border-slate-200 p-3" wire:key="perm-module-{{ \Illuminate\Support\Str::slug($module) }}">
                                <div class="mb-2 text-sm font-semibold text-slate-900">{{ \Illuminate\Support\Str::headline($module) }}</div>
                                @foreach($items as $permission)
                                    <label class="boq-check mb-1.5 flex text-sm font-normal">
                                        <input type="checkbox" wire:model="permissionIds" value="{{ $permission->id }}">
                                        {{ $permission->name }}
                                    </label>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    @error('permissionIds') <p class="boq-field-error">{{ $message }}</p> @enderror
                </fieldset>
            </div>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="cancel">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" icon="fa-floppy-disk" loading="save">{{ __('Save Role') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    <div x-show="confirmDelete" x-cloak class="boq-modal-backdrop" role="dialog" aria-modal="true" x-on:keydown.escape.window="confirmDelete=false">
        <div class="boq-modal boq-modal-sm" @click.outside="confirmDelete=false">
            <div class="boq-modal-head">
                <h2><i class="fas fa-trash" aria-hidden="true"></i> {{ __('Delete role?') }}</h2>
                <button type="button" @click="confirmDelete=false" class="boq-modal-close" aria-label="{{ __('Close') }}"><i class="fas fa-xmark" aria-hidden="true"></i></button>
            </div>
            <div class="boq-modal-body boq-modal-message">{{ __('Delete') }} <strong x-text="deleteName"></strong>{{ __('? This action cannot be undone.') }}</div>
            <div class="boq-modal-foot">
                <button type="button" @click="confirmDelete=false" class="boq-btn-secondary">{{ __('Cancel') }}</button>
                <button type="button" @click="$wire.delete(deleteId); confirmDelete=false" class="boq-btn-danger"><i class="fas fa-trash" aria-hidden="true"></i> {{ __('Delete') }}</button>
            </div>
        </div>
    </div>
</div>
