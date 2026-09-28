<div class="boq-page-stack">
    <x-ui.page-header
        :eyebrow="__('Super Admin')"
        :title="__('Administration & System Control')"
        icon="fa-gauge"
        :subtitle="__('Manage subscriptions, payments, users, access control and system settings.')"
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="fa-gear" :href="route('admin.settings')">{{ __('Settings') }}</x-ui.button>
            <x-ui.button icon="fa-users" :href="route('admin.users')">{{ __('Users') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="boq-stats-grid">
        <x-stat-card :label="__('Users')" :value="\App\Support\Format::number($stats['total_users'], 0)" icon="fa-users" color="green" :href="route('admin.users')" />
        <x-stat-card :label="__('Active Subscriptions')" :value="\App\Support\Format::number($stats['active_subscriptions'], 0)" icon="fa-receipt" color="blue" :href="route('admin.subscriptions')" />
        <x-stat-card :label="__('Active Plans')" :value="\App\Support\Format::number($stats['plans_count'], 0)" icon="fa-layer-group" color="purple" :href="route('admin.plans')" />
        <x-stat-card
            :label="__('Successful Revenue')"
            :value="\App\Support\Regional::currency().' '.\App\Support\Format::compact((float) $stats['total_revenue'])"
            :hint="\App\Support\Format::number((float) $stats['total_revenue'], 0)"
            icon="fa-sack-dollar"
            color="amber"
        />
    </div>

    <div class="boq-panel">
        <div class="flex flex-wrap items-end justify-between gap-3 border-b border-slate-200 pr-4">
            <x-ui.tabs :label="__('Overview sections')" class="w-auto flex-1 border-b-0">
                @foreach($tabs as $key => $label)
                    <x-ui.tab wire:click="$set('activeTab','{{ $key }}')" wire:key="admin-tab-{{ $key }}" :active="$activeTab === $key">{{ __($label) }}</x-ui.tab>
                @endforeach
            </x-ui.tabs>

            <div class="boq-input-icon-wrap mb-2 w-full md:w-72">
                <i class="fas fa-search boq-input-icon" aria-hidden="true"></i>
                <input wire:model.live.debounce.300ms="search" type="search" class="boq-field boq-field-with-icon" placeholder="{{ __('Search current tab...') }}" aria-label="{{ __('Search current tab...') }}">
            </div>
        </div>

        @if($activeTab === 'subscriptions')
            <x-ui.table>
                <thead>
                    <tr>
                        <th>{{ __('User') }}</th>
                        <th>{{ __('Plan') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Period') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($subscriptions as $sub)
                        <tr wire:key="overview-sub-{{ $sub->id }}">
                            <td>
                                <div class="boq-table-title">{{ $sub->user?->name ?? __('Deleted user') }}</div>
                                <div class="boq-table-subtitle">{{ $sub->user?->email }}</div>
                            </td>
                            <td>{{ $sub->plan?->name ?? '—' }}</td>
                            <td><x-ui.status :status="$sub->status" /></td>
                            <td class="whitespace-nowrap text-slate-500">{{ \App\Support\Format::date($sub->start_date) ?? '—' }} – {{ \App\Support\Format::date($sub->end_date) ?? __('Ongoing') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-0"><x-ui.empty-state icon="fa-receipt" :title="__('No subscriptions found.')" /></td></tr>
                    @endforelse
                </tbody>
            </x-ui.table>
            @if($subscriptions->hasPages())<div class="boq-pagination">{{ $subscriptions->links() }}</div>@endif
        @elseif($activeTab === 'plans')
            <x-ui.table>
                <thead>
                    <tr>
                        <th>{{ __('Plan') }}</th>
                        <th class="text-right">{{ __('Price') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($plans as $plan)
                        <tr wire:key="overview-plan-{{ $plan->id }}">
                            <td>
                                <div class="boq-table-title">{{ $plan->name }}</div>
                                <div class="boq-table-subtitle">{{ $plan->code }}</div>
                            </td>
                            <td class="is-numeric"><x-money :amount="$plan->price" :currency="$plan->currency" /></td>
                            <td><x-ui.status :status="$plan->is_active ? 'active' : 'inactive'" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="p-0"><x-ui.empty-state icon="fa-layer-group" :title="__('No plans found.')" /></td></tr>
                    @endforelse
                </tbody>
            </x-ui.table>
            @if($plans->hasPages())<div class="boq-pagination">{{ $plans->links() }}</div>@endif
        @elseif($activeTab === 'users')
            <x-ui.table>
                <thead>
                    <tr>
                        <th>{{ __('User') }}</th>
                        <th>{{ __('Organisation') }}</th>
                        <th>{{ __('Roles') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr wire:key="overview-user-{{ $user->id }}">
                            <td>
                                <div class="boq-table-title">{{ $user->name }}</div>
                                <div class="boq-table-subtitle">{{ $user->email }}</div>
                            </td>
                            <td>{{ $user->organisation?->name ?? '—' }}</td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    @forelse($user->roles as $role)
                                        <x-ui.badge color="brand">{{ $role->name }}</x-ui.badge>
                                    @empty
                                        <span class="boq-table-empty">—</span>
                                    @endforelse
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="p-0"><x-ui.empty-state icon="fa-users" :title="__('No users found.')" /></td></tr>
                    @endforelse
                </tbody>
            </x-ui.table>
            @if($users->hasPages())<div class="boq-pagination">{{ $users->links() }}</div>@endif
        @endif
    </div>
</div>
