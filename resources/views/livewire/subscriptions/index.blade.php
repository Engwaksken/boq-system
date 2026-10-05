@php
    $typeIcons = ['monthly' => 'fa-calendar-days', 'quarterly' => 'fa-calendar-week', 'three_month' => 'fa-calendar-week', 'six_month' => 'fa-calendar-week', 'annual' => 'fa-calendar-check', 'lifetime' => 'fa-infinity'];
    $subscriptionStatuses = ['pending', 'trial', 'active', 'past_due', 'grace_period', 'suspended', 'expired', 'cancelled'];
    $isDanger = in_array($actionType, ['cancel', 'bulk_cancel'], true);
@endphp

<div class="boq-subscriptions-page">

    <x-ui.page-header
        :title="__('Subscriptions')"
        icon="fa-credit-card"
        :subtitle="__('Manage your subscription history and choose an available plan.')"
    >
        <x-slot:actions>
            @if($currentSubscription)
                <div class="boq-current-subscription">
                    <div class="boq-current-subscription-label">{{ __('Current Subscription') }}</div>
                    <div class="boq-current-subscription-name">{{ $currentSubscription->plan?->name ?? __('Plan') }}</div>
                    @if($currentSubscription->end_date)
                        <div class="mt-0.5 text-xs text-slate-500">{{ __('Until') }} <x-date :value="$currentSubscription->end_date" /></div>
                    @endif
                </div>
            @endif
            <x-ui.button variant="secondary" icon="fa-gift" :href="route('topups.index')">{{ __('Top-ups') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.flash :keys="['message', 'status', 'error']" :types="['message' => 'info']" />

    <div class="boq-stats-grid">
        <x-stat-card :label="__('Total Subscriptions')" :value="\App\Support\Format::number($stats['total'] ?? 0, 0)" icon="fa-receipt" color="green" />
        <x-stat-card :label="__('Active')" :value="\App\Support\Format::number($stats['active'] ?? 0, 0)" icon="fa-circle-check" color="blue" />
        <x-stat-card :label="__('Pending')" :value="\App\Support\Format::number($stats['pending'] ?? 0, 0)" icon="fa-clock" color="amber" />
        <x-stat-card
            :label="__('Available Plans')"
            :value="\App\Support\Format::number($stats['plans'] ?? 0, 0)"
            icon="fa-layer-group"
            color="purple"
            :active="$activeTab === 'plans'"
            wire:click="showPlans"
        />
    </div>

    <div class="boq-panel boq-subscription-panel">

        <x-ui.tabs :label="__('Subscription sections')">
            <x-ui.tab
                icon="fa-credit-card"
                :active="$activeTab === 'subscriptions'"
                wire:click.prevent="showSubscriptions"
                wire:loading.attr="disabled"
                wire:target="showSubscriptions,showPlans"
            >{{ __('Subscriptions') }}</x-ui.tab>

            <x-ui.tab
                icon="fa-layer-group"
                :active="$activeTab === 'plans'"
                wire:click.prevent="showPlans"
                wire:loading.attr="disabled"
                wire:target="showSubscriptions,showPlans"
            >{{ __('Available Plans') }}</x-ui.tab>
        </x-ui.tabs>

        <div wire:loading.flex wire:target="showSubscriptions,showPlans" class="boq-tab-loading">
            <i class="fas fa-spinner fa-spin" aria-hidden="true"></i>
            {{ __('Loading...') }}
        </div>

        @if($activeTab === 'subscriptions')
            <div wire:key="subscriptions-tab">
                <div class="boq-filter-section">
                    <div class="boq-subscription-filter-grid">
                        <x-ui.field :label="__('Search')" for="subscription-search">
                            <div class="boq-input-icon-wrap">
                                <i class="fas fa-search boq-input-icon" aria-hidden="true"></i>
                                <input
                                    id="subscription-search"
                                    type="search"
                                    wire:model.live.debounce.300ms="search"
                                    class="boq-field boq-field-with-icon"
                                    placeholder="{{ __('Search plan, status or payment...') }}"
                                    autocomplete="off"
                                >
                            </div>
                        </x-ui.field>

                        <x-ui.field :label="__('Status')" for="subscription-status">
                            <select id="subscription-status" wire:model.live="statusFilter" class="boq-field">
                                <option value="all">{{ __('All statuses') }}</option>
                                @foreach($subscriptionStatuses as $status)
                                    <option value="{{ $status }}">{{ __(ucwords(str_replace('_', ' ', $status))) }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>

                        <x-ui.field :label="__('Period')" for="subscription-period">
                            <select id="subscription-period" wire:model.live="periodFilter" class="boq-field">
                                <option value="all">{{ __('All periods') }}</option>
                                <option value="current">{{ __('Current') }}</option>
                                <option value="ending_30">{{ __('Ending in 30 days') }}</option>
                                <option value="expired">{{ __('Expired') }}</option>
                                <option value="this_year">{{ __('Created this year') }}</option>
                            </select>
                        </x-ui.field>

                        <x-ui.field :label="__('Rows')" for="subscription-per-page">
                            <select id="subscription-per-page" wire:model.live="perPage" class="boq-field">
                                @foreach([10, 25, 50, 100] as $size)
                                    <option value="{{ $size }}">{{ $size }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>
                    </div>
                </div>

                <x-bulk-bar :count="count($selectedSubscriptions)">
                    <button type="button" wire:click="confirmBulkCancel" class="boq-btn-danger">
                        <i class="fas fa-ban" aria-hidden="true"></i>
                        {{ __('Cancel selected') }}
                    </button>
                </x-bulk-bar>

                <x-ui.table>
                    <thead>
                        <tr>
                            <th class="boq-checkbox-column">
                                <input type="checkbox" class="boq-checkbox" wire:model.live="selectPage" aria-label="{{ __('Select all subscriptions on this page') }}">
                            </th>
                            <th>{{ __('Plan') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Payment') }}</th>
                            <th>{{ __('Period') }}</th>
                            <th class="text-right">{{ __('Price') }}</th>
                            <th class="text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($subscriptions as $item)
                            <tr wire:key="subscription-{{ $item->id }}">
                                <td class="boq-checkbox-column">
                                    <input
                                        type="checkbox"
                                        class="boq-checkbox"
                                        wire:model.live="selectedSubscriptions"
                                        value="{{ $item->id }}"
                                        aria-label="{{ __('Select :name', ['name' => $item->plan?->name ?? __('subscription')]) }}"
                                    >
                                </td>

                                <td>
                                    <div class="boq-table-title">{{ $item->plan?->name ?? __('Unknown plan') }}</div>
                                    @if($item->plan?->code)
                                        <div class="boq-table-subtitle">{{ $item->plan->code }}</div>
                                    @endif
                                </td>

                                <td><x-ui.status :status="$item->status ?? 'unknown'" /></td>

                                <td><x-ui.status :status="$item->payment_status ?? 'pending'" /></td>

                                <td>
                                    <div class="boq-cell-stack">
                                        <span class="boq-cell-with-icon">
                                            <i class="fas fa-calendar-day" aria-hidden="true"></i>
                                            {{ \App\Support\Format::date($item->start_date) ?? __('Not started') }}
                                        </span>
                                        <span class="boq-table-subtitle">
                                            {{ __('Until') }} {{ \App\Support\Format::date($item->end_date) ?? __('Ongoing') }}
                                        </span>
                                    </div>
                                </td>

                                <td class="is-numeric font-semibold text-slate-900">
                                    <x-money :amount="$item->plan?->price ?? 0" :currency="$item->plan?->currency" />
                                </td>

                                <td class="text-right">
                                    <div class="boq-table-actions">
                                        @if(in_array($item->status, ['pending', 'past_due'], true))
                                            <a
                                                href="{{ route('checkout', ['type' => 'plan', 'id' => $item->id]) }}"
                                                class="boq-btn-primary boq-btn-sm"
                                            >
                                                <i class="fas fa-credit-card" aria-hidden="true"></i>
                                                {{ __('Pay now') }}
                                            </a>
                                        @endif

                                        @unless(in_array($item->status, ['cancelled', 'expired'], true))
                                            <button
                                                type="button"
                                                wire:click="confirmCancel({{ $item->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="confirmCancel({{ $item->id }})"
                                                class="boq-icon-btn boq-icon-danger"
                                                title="{{ __('Cancel subscription') }}"
                                                aria-label="{{ __('Cancel subscription') }}"
                                            >
                                                <i class="fas fa-ban" aria-hidden="true"></i>
                                            </button>
                                        @else
                                            <span class="boq-no-action">{{ __('No action') }}</span>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-0">
                                    <x-ui.empty-state
                                        icon="fa-receipt"
                                        :title="__('No subscriptions match your filters.')"
                                        :description="__('Pick a plan to get started, or change the filters above.')"
                                    >
                                        <x-ui.button size="sm" icon="fa-layer-group" wire:click="showPlans">{{ __('Available Plans') }}</x-ui.button>
                                    </x-ui.empty-state>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.table>

                @if($subscriptions->hasPages())
                    <div class="boq-pagination">
                        {{ $subscriptions->links() }}
                    </div>
                @endif
            </div>
        @else
            <div wire:key="plans-tab">
                <div class="boq-filter-section">
                    <div class="boq-plan-filter-grid">
                        <x-ui.field :label="__('Search Plans')" for="plan-search">
                            <div class="boq-input-icon-wrap">
                                <i class="fas fa-search boq-input-icon" aria-hidden="true"></i>
                                <input
                                    id="plan-search"
                                    type="search"
                                    wire:model.live.debounce.300ms="planSearch"
                                    class="boq-field boq-field-with-icon"
                                    placeholder="{{ __('Search plan name, code or description...') }}"
                                    autocomplete="off"
                                >
                            </div>
                        </x-ui.field>

                        <x-ui.field :label="__('Billing Period')" for="plan-period">
                            <select id="plan-period" wire:model.live="planPeriodFilter" class="boq-field">
                                <option value="all">{{ __('All periods') }}</option>
                                <option value="monthly">{{ __('Monthly') }}</option>
                                <option value="quarterly">{{ __('3 months') }}</option>
                                <option value="six_month">{{ __('6 months') }}</option>
                                <option value="annual">{{ __('Annual') }}</option>
                                <option value="lifetime">{{ __('Lifetime') }}</option>
                            </select>
                        </x-ui.field>

                        <x-ui.field :label="__('Rows')" for="plan-per-page">
                            <select id="plan-per-page" wire:model.live="planPerPage" class="boq-field">
                                @foreach([10, 25, 50, 100] as $size)
                                    <option value="{{ $size }}">{{ $size }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>
                    </div>
                </div>

                <div class="boq-plan-grid p-4 sm:p-5">
                    @forelse($plans as $plan)
                        @php $isCurrent = $currentSubscription && (int) $currentSubscription->plan_id === (int) $plan->id; @endphp

                        <article wire:key="available-plan-{{ $plan->id }}" @class(['boq-plan-card', 'is-current' => $isCurrent])>
                            <div class="boq-plan-header">
                                <div class="boq-plan-heading-copy">
                                    <span class="boq-plan-main-icon"><i class="fas {{ $typeIcons[$plan->type] ?? 'fa-box-open' }}" aria-hidden="true"></i></span>
                                    <h3 class="boq-plan-name">{{ $plan->name }}</h3>
                                    <p class="boq-plan-description">{{ $plan->description ?: __('BOQ subscription plan') }}</p>
                                </div>

                                <span class="boq-plan-type">{{ __(\Illuminate\Support\Str::headline($plan->type ?? 'plan')) }}</span>
                            </div>

                            <div class="boq-plan-price">
                                <span class="boq-plan-currency">{{ $plan->currency ?? \App\Support\Regional::currency() }}</span>
                                <span class="boq-plan-price-value">{{ \App\Support\Format::number((float) $plan->price, 0) }}</span>
                            </div>

                            <div class="boq-plan-duration">
                                <i class="fas fa-clock" aria-hidden="true"></i>
                                @if($plan->type === 'lifetime')
                                    <span>{{ __('Lifetime access') }}</span>
                                @elseif($plan->duration_hours)
                                    <span>{{ trans_choice(':count hour|:count hours', (int) $plan->duration_hours, ['count' => \App\Support\Format::number($plan->duration_hours, 0)]) }}</span>
                                @elseif($plan->duration_days)
                                    <span>{{ trans_choice(':count day|:count days', (int) $plan->duration_days, ['count' => \App\Support\Format::number($plan->duration_days, 0)]) }}</span>
                                @else
                                    <span>{{ __('Duration based on plan') }}</span>
                                @endif
                            </div>

                            <div class="boq-plan-limits">
                                <div class="boq-plan-limits-grid">
                                    <div class="boq-plan-limit">
                                        <div class="boq-plan-limit-label"><i class="fas fa-folder-open" aria-hidden="true"></i> {{ __('Projects') }}</div>
                                        <div class="boq-plan-limit-value">{{ $plan->max_projects !== null ? \App\Support\Format::number($plan->max_projects, 0) : '∞' }}</div>
                                    </div>
                                    <div class="boq-plan-limit">
                                        <div class="boq-plan-limit-label"><i class="fas fa-file-invoice-dollar" aria-hidden="true"></i> {{ __('BOQs') }}</div>
                                        <div class="boq-plan-limit-value">{{ $plan->max_boqs !== null ? \App\Support\Format::number($plan->max_boqs, 0) : '∞' }}</div>
                                    </div>
                                </div>
                            </div>

                            @if($plan->max_users || $plan->max_ocr_pages || $plan->max_storage_bytes)
                                <div class="boq-plan-secondary-limits">
                                    @if($plan->max_users)
                                        <div><i class="fas fa-users" aria-hidden="true"></i> {{ trans_choice(':count user|:count users', (int) $plan->max_users, ['count' => \App\Support\Format::number($plan->max_users, 0)]) }}</div>
                                    @endif
                                    @if($plan->max_ocr_pages)
                                        <div><i class="fas fa-file-lines" aria-hidden="true"></i> {{ __(':count OCR pages', ['count' => \App\Support\Format::number($plan->max_ocr_pages, 0)]) }}</div>
                                    @endif
                                    @if($plan->max_storage_bytes)
                                        <div><i class="fas fa-database" aria-hidden="true"></i> {{ __(':size MB storage', ['size' => \App\Support\Format::number($plan->max_storage_bytes / 1024 / 1024, 0)]) }}</div>
                                    @endif
                                </div>
                            @endif

                            @if($plan->relationLoaded('features') && $plan->features->isNotEmpty())
                                <div class="boq-plan-features">
                                    <p class="boq-plan-features-title">{{ __('Features') }}</p>
                                    <ul class="boq-plan-feature-list">
                                        @foreach($plan->features->take(5) as $feature)
                                            <li class="boq-plan-feature">
                                                <span class="boq-plan-feature-check" aria-hidden="true"><i class="fas fa-check"></i></span>
                                                <span>{{ $feature->name }}</span>
                                            </li>
                                        @endforeach
                                    </ul>

                                    @if($plan->features->count() > 5)
                                        <p class="boq-plan-more-features">
                                            <i class="fas fa-plus-circle" aria-hidden="true"></i>
                                            {{ trans_choice(':count more feature|:count more features', $plan->features->count() - 5, ['count' => $plan->features->count() - 5]) }}
                                        </p>
                                    @endif
                                </div>
                            @endif

                            @if(($plan->has_trial ?? false) && ($plan->trial_days ?? 0) > 0)
                                <div class="boq-plan-trial">
                                    <i class="fas fa-gift" aria-hidden="true"></i>
                                    {{ __(':days-day free trial', ['days' => $plan->trial_days]) }}
                                </div>
                            @endif

                            <div class="boq-plan-action">
                                @if($isCurrent)
                                    <span class="boq-current-plan-button">
                                        <i class="fas fa-circle-check" aria-hidden="true"></i>
                                        {{ __('Current Plan') }}
                                    </span>
                                @else
                                    <button
                                        type="button"
                                        wire:click="confirmSubscribe({{ $plan->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="confirmSubscribe({{ $plan->id }})"
                                        class="boq-plan-choose-button"
                                    >
                                        <i wire:loading.remove wire:target="confirmSubscribe({{ $plan->id }})" class="fas fa-circle-check" aria-hidden="true"></i>
                                        <i wire:loading wire:target="confirmSubscribe({{ $plan->id }})" class="fas fa-spinner fa-spin" aria-hidden="true"></i>
                                        <span wire:loading.remove wire:target="confirmSubscribe({{ $plan->id }})">{{ __('Choose Plan') }}</span>
                                        <span wire:loading wire:target="confirmSubscribe({{ $plan->id }})">{{ __('Processing...') }}</span>
                                    </button>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="boq-plans-empty">
                            <i class="fas fa-layer-group" aria-hidden="true"></i>
                            <p>{{ __('No available plans match your search.') }}</p>
                        </div>
                    @endforelse
                </div>

                @if($plans->hasPages())
                    <div class="boq-pagination">
                        {{ $plans->links() }}
                    </div>
                @endif
            </div>
        @endif
    </div>

    @if($showActionModal)
        <x-ui.modal
            wire:key="subscription-action-modal"
            id="subscription-action"
            :title="$actionTitle"
            :icon="$isDanger ? 'fa-ban' : 'fa-circle-check'"
            size="sm"
            close="closeActionModal"
        >
            <p class="boq-modal-message">{{ $actionMessage }}</p>

            <x-slot:footer>
                <button type="button" wire:click="closeActionModal" wire:loading.attr="disabled" wire:target="performAction" class="boq-btn-secondary">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                    {{ __('Back') }}
                </button>

                <button type="button" wire:click="performAction" wire:loading.attr="disabled" wire:target="performAction" class="{{ $isDanger ? 'boq-btn-danger' : 'boq-btn-primary' }}">
                    <i wire:loading.remove wire:target="performAction" class="fas {{ $isDanger ? 'fa-ban' : 'fa-check' }}" aria-hidden="true"></i>
                    <i wire:loading wire:target="performAction" class="fas fa-spinner fa-spin" aria-hidden="true"></i>
                    <span wire:loading.remove wire:target="performAction">{{ __('Confirm') }}</span>
                    <span wire:loading wire:target="performAction">{{ __('Processing...') }}</span>
                </button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
