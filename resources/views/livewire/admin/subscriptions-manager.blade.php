<div class="boq-page-stack">
    <x-ui.page-header
        :title="__('Subscriptions Management')"
        icon="fa-receipt"
        :subtitle="__('Manage user subscriptions, statuses and subscription revenue.')"
    />

    <x-ui.flash :keys="['message', 'status', 'error']" />

    <div class="boq-admin-stats-grid">
        <x-stat-card :label="__('Total Subscriptions')" :value="\App\Support\Format::number($stats['total'] ?? 0, 0)" icon="fa-receipt" color="blue" />
        <x-stat-card :label="__('Active')" :value="\App\Support\Format::number($stats['active'] ?? 0, 0)" icon="fa-circle-check" color="green" />
        <x-stat-card :label="__('Expired')" :value="\App\Support\Format::number($stats['expired'] ?? 0, 0)" icon="fa-calendar-xmark" color="red" />
        <x-stat-card :label="__('Cancelled')" :value="\App\Support\Format::number($stats['cancelled'] ?? 0, 0)" icon="fa-ban" color="amber" />
        <x-stat-card
            :label="__('Monthly Revenue')"
            :value="\App\Support\Regional::currency().' '.\App\Support\Format::compact((float) ($stats['revenue'] ?? 0))"
            :hint="\App\Support\Format::number((float) ($stats['revenue'] ?? 0), 0)"
            icon="fa-coins"
            color="purple"
        />
    </div>

    <div class="boq-panel">
        <div class="boq-toolbar border-b border-slate-200">
            <x-ui.field :label="__('Search')" for="admin-sub-search" class="boq-toolbar-grow">
                <div class="boq-input-icon-wrap">
                    <i class="fas fa-search boq-input-icon" aria-hidden="true"></i>
                    <input id="admin-sub-search" type="search" wire:model.live.debounce.300ms="search" class="boq-field boq-field-with-icon" placeholder="{{ __('Search user, email, plan or status...') }}">
                </div>
            </x-ui.field>

            <x-ui.field :label="__('Rows')" for="admin-sub-rows" class="w-full sm:w-24">
                <select id="admin-sub-rows" wire:model.live="perPage" class="boq-field">
                    @foreach($perPageOptions as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </div>

        <x-bulk-bar :count="count($selected)">
            <button type="button" wire:click="bulkActivate" wire:confirm="{{ __('Activate the selected pending, overdue, failed or expired subscriptions?') }}" class="boq-btn-secondary"><i class="fas fa-circle-check" aria-hidden="true"></i> {{ __('Activate') }}</button>
            <button type="button" wire:click="bulkCancel" wire:confirm="{{ __('Cancel the selected subscriptions and revoke their access?') }}" class="boq-btn-secondary"><i class="fas fa-ban" aria-hidden="true"></i> {{ __('Cancel') }}</button>
            <button type="button" wire:click="bulkDelete" wire:confirm="{{ __('Delete the selected subscriptions? Active ones are skipped.') }}" class="boq-btn-danger"><i class="fas fa-trash" aria-hidden="true"></i> {{ __('Delete') }}</button>
        </x-bulk-bar>

        <x-ui.table>
            <thead>
                <tr>
                    <th class="boq-check-col"><x-select-all :ids="$subscriptions->pluck('id')" :selected="$selected" /></th>
                    <x-ui.sort-header field="user.name" :sort-by="$sortBy" :sort-dir="$sortDir">{{ __('User') }}</x-ui.sort-header>
                    <x-ui.sort-header field="plan.name" :sort-by="$sortBy" :sort-dir="$sortDir">{{ __('Plan') }}</x-ui.sort-header>
                    <x-ui.sort-header field="status" :sort-by="$sortBy" :sort-dir="$sortDir">{{ __('Status') }}</x-ui.sort-header>
                    <th class="text-right">{{ __('Amount') }}</th>
                    <x-ui.sort-header field="start_date" :sort-by="$sortBy" :sort-dir="$sortDir">{{ __('Start Date') }}</x-ui.sort-header>
                    <x-ui.sort-header field="end_date" :sort-by="$sortBy" :sort-dir="$sortDir">{{ __('End Date') }}</x-ui.sort-header>
                    <th class="text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>

            <tbody>
                @forelse($subscriptions as $sub)
                    <tr wire:key="admin-subscription-{{ $sub->id }}">
                        <td class="boq-check-col"><x-select-row :id="$sub->id" /></td>
                        <td>
                            <div class="boq-table-title">{{ $sub->user?->name ?? __('Deleted user') }}</div>
                            <div class="boq-table-subtitle">{{ $sub->user?->email ?? '—' }}</div>
                        </td>
                        <td><span class="boq-cell-with-icon"><i class="fas fa-layer-group" aria-hidden="true"></i> {{ $sub->plan?->name ?? __('Plan unavailable') }}</span></td>
                        <td><x-ui.status :status="$sub->status ?? 'unknown'" /></td>
                        <td class="is-numeric font-semibold text-slate-900"><x-money :amount="$sub->plan?->price ?? 0" :currency="$sub->plan?->currency" /></td>
                        <td class="whitespace-nowrap">{{ \App\Support\Format::date($sub->start_date) ?? __('Not started') }}</td>
                        <td class="whitespace-nowrap">{{ \App\Support\Format::date($sub->end_date) ?? __('Ongoing') }}</td>
                        <td class="text-right">
                            <div class="boq-table-actions">
                                @if(in_array($sub->status, ['pending', 'past_due', 'failed', 'expired'], true))
                                    <button type="button" wire:click="activate({{ $sub->id }})" wire:confirm="{{ __('Activate this subscription now? Use this for payments confirmed outside the system.') }}" class="boq-icon-btn boq-icon-success" title="{{ __('Activate') }}" aria-label="{{ __('Activate') }}">
                                        <i class="fas fa-circle-check" aria-hidden="true"></i>
                                    </button>
                                @endif

                                @if($sub->end_date && ! in_array($sub->status, ['cancelled'], true))
                                    <button type="button" wire:click="openExtend({{ $sub->id }})" class="boq-icon-btn" title="{{ __('Extend') }}" aria-label="{{ __('Extend') }}">
                                        <i class="fas fa-calendar-plus" aria-hidden="true"></i>
                                    </button>
                                @endif

                                @unless(in_array($sub->status, ['cancelled', 'expired'], true))
                                    <button type="button" wire:click="cancel({{ $sub->id }})" wire:confirm="{{ __('Cancel this subscription and revoke its access?') }}" class="boq-icon-btn boq-icon-danger" title="{{ __('Cancel') }}" aria-label="{{ __('Cancel') }}">
                                        <i class="fas fa-ban" aria-hidden="true"></i>
                                    </button>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="p-0"><x-ui.empty-state icon="fa-receipt" :title="__('No subscriptions found.')" /></td></tr>
                @endforelse
            </tbody>
        </x-ui.table>

        @if($subscriptions->hasPages())
            <div class="boq-pagination">{{ $subscriptions->links() }}</div>
        @endif
    </div>

    @if($extendingId)
        <x-ui.modal wire:key="extend-modal" id="extend" :title="__('Extend Subscription')" icon="fa-calendar-plus" size="sm" close="closeExtend" submit="applyExtension">
            <x-ui.field :label="__('Add days')" for="extend-days" error="extendDays" required :hint="__('Counted from the current end date, or from today if it has already expired.')">
                <input placeholder="30" id="extend-days" type="number" min="1" max="3650" wire:model="extendDays" class="boq-field @error('extendDays') has-error @enderror">
            </x-ui.field>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="closeExtend">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" icon="fa-calendar-check" loading="applyExtension">{{ __('Extend') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
