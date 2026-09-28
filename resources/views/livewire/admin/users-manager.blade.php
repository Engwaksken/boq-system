<div class="boq-page-stack" x-data="{ confirmStatus: false, statusId: null, statusName: '', statusAction: '', confirmRole: false, roleUserId: null, roleId: null, roleName: '', roleUserName: '' }">
    <x-ui.page-header
        :title="__('Users')"
        icon="fa-users"
        :subtitle="__('Manage account access, roles and subscription links.')"
    >
        <x-slot:actions>
            <x-ui.button icon="fa-user-plus" wire:click="$set('showCreate', true)">{{ __('Add user') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.flash :keys="['message', 'status', 'error']" />

    <div class="boq-panel">
        <div class="boq-toolbar border-b border-slate-200">
            <x-ui.field :label="__('Search')" for="user-search" class="boq-toolbar-grow">
                <div class="boq-input-icon-wrap">
                    <i class="fas fa-search boq-input-icon" aria-hidden="true"></i>
                    <input id="user-search" type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search name or email') }}" class="boq-field boq-field-with-icon">
                </div>
            </x-ui.field>

            <x-ui.field :label="__('Status')" for="user-status" class="w-full sm:w-44">
                <select id="user-status" wire:model.live="status" class="boq-field">
                    <option value="all">{{ __('All users') }}</option>
                    <option value="active">{{ __('Active') }}</option>
                    <option value="inactive">{{ __('Inactive') }}</option>
                </select>
            </x-ui.field>
        </div>

        <x-bulk-bar :count="count($selected)">
            <button type="button" wire:click="bulkSetActive(true)" class="boq-btn-secondary"><i class="fas fa-user-check" aria-hidden="true"></i> {{ __('Enable') }}</button>
            <button type="button" wire:click="bulkSetActive(false)" wire:confirm="{{ __('Disable the selected users? Your own account is skipped.') }}" class="boq-btn-secondary"><i class="fas fa-user-slash" aria-hidden="true"></i> {{ __('Disable') }}</button>
            <select wire:change="bulkAssignRole($event.target.value)" class="boq-field boq-field-sm" aria-label="{{ __('Assign role to selected users') }}">
                <option value="">{{ __('Assign role...') }}</option>
                @foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach
            </select>
        </x-bulk-bar>

        <x-ui.table>
            <thead>
                <tr>
                    <th class="boq-check-col"><x-select-all :ids="$users->pluck('id')" :selected="$selected" /></th>
                    <th>{{ __('User') }}</th>
                    <th>{{ __('Organisation') }}</th>
                    <th>{{ __('Roles') }}</th>
                    <th>{{ __('Subscription') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    @php $sub = $user->subscriptions->sortByDesc('created_at')->first(); @endphp
                    <tr wire:key="user-{{ $user->id }}">
                        <td class="boq-check-col"><x-select-row :id="$user->id" /></td>
                        <td>
                            <div class="flex items-center gap-3">
                                <span class="boq-avatar">
                                    @if($user->avatar_url)
                                        <img src="{{ $user->avatar_url }}" alt="">
                                    @else
                                        {{ mb_strtoupper(mb_substr((string) $user->name, 0, 1)) }}
                                    @endif
                                </span>
                                <div class="min-w-0">
                                    <div class="boq-table-title">{{ $user->name }}</div>
                                    <div class="boq-table-subtitle">{{ $user->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td>{{ $user->organisation?->name ?? '—' }}</td>
                        <td class="min-w-[12rem]">
                            <div class="flex flex-wrap gap-1">
                                @foreach($user->roles as $role)
                                    <button
                                        type="button"
                                        @click="roleUserId={{ $user->id }}; roleId={{ $role->id }}; roleName=@js($role->name); roleUserName=@js($user->name); confirmRole=true"
                                        class="boq-badge boq-badge-brand hover:border-red-200 hover:bg-red-50 hover:text-red-700"
                                        title="{{ __('Remove role') }}"
                                    >
                                        {{ $role->name }} <i class="fas fa-xmark" aria-hidden="true"></i>
                                    </button>
                                @endforeach
                            </div>
                            <select wire:change="assignRole({{ $user->id }}, $event.target.value)" class="boq-field boq-field-sm mt-2" aria-label="{{ __('Add role...') }}">
                                <option value="">{{ __('Add role...') }}</option>
                                @foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach
                            </select>
                        </td>
                        <td>
                            @if($sub)
                                <div class="boq-table-title">{{ $sub->plan?->name ?? __('Unknown plan') }}</div>
                                <x-ui.status class="mt-1" :status="$sub->status" />
                            @else
                                <span class="boq-table-empty">{{ __('None') }}</span>
                            @endif
                        </td>
                        <td><x-ui.status :status="$user->is_active ? 'active' : 'inactive'" /></td>
                        <td class="text-right">
                            <button
                                type="button"
                                @click="statusId={{ $user->id }}; statusName=@js($user->name); statusAction=@js($user->is_active ? __('Disable') : __('Enable')); confirmStatus=true"
                                class="{{ $user->is_active ? 'boq-btn-secondary text-red-600' : 'boq-btn-soft' }} boq-btn-sm"
                            >
                                <i class="fas {{ $user->is_active ? 'fa-user-slash' : 'fa-user-check' }}" aria-hidden="true"></i>
                                {{ $user->is_active ? __('Disable') : __('Enable') }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-0"><x-ui.empty-state icon="fa-users" :title="__('No users found.')" /></td></tr>
                @endforelse
            </tbody>
        </x-ui.table>

        @if($users->hasPages())
            <div class="boq-pagination">{{ $users->links() }}</div>
        @endif
    </div>

    @if($showCreate)
        <x-ui.modal wire:key="user-create-modal" id="user-create" :title="__('Add user')" icon="fa-user-plus" close="$set('showCreate', false)" submit="createUser">
            <div class="boq-form-grid">
                <x-ui.field :label="__('Full name')" for="new-user-name" error="newName" required>
                    <input id="new-user-name" wire:model="newName" type="text" class="boq-field @error('newName') has-error @enderror" placeholder="{{ __('e.g. Jane Doe') }}" autocomplete="off">
                </x-ui.field>

                <x-ui.field :label="__('Email')" for="new-user-email" error="newEmail" required>
                    <input id="new-user-email" wire:model="newEmail" type="email" class="boq-field @error('newEmail') has-error @enderror" placeholder="name@company.com" autocomplete="off">
                </x-ui.field>

                <x-ui.field :label="__('Temporary password')" for="new-user-password" error="newPassword" required>
                    <x-password-input id="new-user-password" wire:model="newPassword" placeholder="{{ __('min. 8 characters') }}" autocomplete="new-password" />
                </x-ui.field>

                <x-ui.field :label="__('Phone (optional)')" for="new-user-phone" error="newPhone" :hint="__('Include the country code.')">
                    <input id="new-user-phone" wire:model="newPhone" type="tel" class="boq-field @error('newPhone') has-error @enderror" placeholder="+1 202 555 0143">
                </x-ui.field>

                <x-ui.field :label="__('Organisation')" for="new-user-org" error="newOrganisationId">
                    <select id="new-user-org" wire:model="newOrganisationId" class="boq-field">
                        <option value="0">{{ __('None (personal account)') }}</option>
                        @foreach($organisations as $org)<option value="{{ $org->id }}">{{ $org->name }}</option>@endforeach
                    </select>
                </x-ui.field>

                <x-ui.field :label="__('Role')" for="new-user-role" error="newRoleId" required>
                    <select id="new-user-role" wire:model="newRoleId" class="boq-field @error('newRoleId') has-error @enderror">
                        <option value="">{{ __('Select...') }}</option>
                        @foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach
                    </select>
                </x-ui.field>
            </div>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="$set('showCreate', false)">{{ __('Cancel') }}</x-ui.button>
                <button type="submit" class="boq-btn-primary" wire:loading.attr="disabled" wire:target="createUser">
                    <span wire:loading.remove wire:target="createUser"><i class="fas fa-user-plus" aria-hidden="true"></i> {{ __('Create user') }}</span>
                    <span wire:loading wire:target="createUser"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i> {{ __('Creating...') }}</span>
                </button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    <div x-show="confirmStatus" x-cloak class="boq-modal-backdrop" role="dialog" aria-modal="true" x-on:keydown.escape.window="confirmStatus=false">
        <div class="boq-modal boq-modal-sm" @click.outside="confirmStatus=false">
            <div class="boq-modal-head">
                <h2><span x-text="statusAction"></span> {{ __('user?') }}</h2>
                <button type="button" @click="confirmStatus=false" class="boq-modal-close" aria-label="{{ __('Close') }}"><i class="fas fa-xmark" aria-hidden="true"></i></button>
            </div>
            <div class="boq-modal-body boq-modal-message"><span x-text="statusAction"></span> {{ __('access for') }} <strong x-text="statusName"></strong>?</div>
            <div class="boq-modal-foot">
                <button type="button" @click="confirmStatus=false" class="boq-btn-secondary">{{ __('Cancel') }}</button>
                <button type="button" @click="$wire.toggleActive(statusId); confirmStatus=false" class="boq-btn-primary">{{ __('Confirm') }}</button>
            </div>
        </div>
    </div>

    <div x-show="confirmRole" x-cloak class="boq-modal-backdrop" role="dialog" aria-modal="true" x-on:keydown.escape.window="confirmRole=false">
        <div class="boq-modal boq-modal-sm" @click.outside="confirmRole=false">
            <div class="boq-modal-head">
                <h2>{{ __('Remove role?') }}</h2>
                <button type="button" @click="confirmRole=false" class="boq-modal-close" aria-label="{{ __('Close') }}"><i class="fas fa-xmark" aria-hidden="true"></i></button>
            </div>
            <div class="boq-modal-body boq-modal-message">{{ __('Remove') }} <strong x-text="roleName"></strong> {{ __('from') }} <strong x-text="roleUserName"></strong>?</div>
            <div class="boq-modal-foot">
                <button type="button" @click="confirmRole=false" class="boq-btn-secondary">{{ __('Cancel') }}</button>
                <button type="button" @click="$wire.removeRole(roleUserId, roleId); confirmRole=false" class="boq-btn-danger">{{ __('Remove') }}</button>
            </div>
        </div>
    </div>
</div>
