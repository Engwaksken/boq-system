@php
    $typeIcons = ['feature_update' => 'fa-wand-magic-sparkles', 'usage_credits' => 'fa-coins', 'unlock' => 'fa-unlock', 'storage' => 'fa-database'];
@endphp

<div class="boq-subscriptions-page">

    <x-ui.page-header
        :title="__('Top-ups & Add-ons')"
        icon="fa-gift"
        :subtitle="__('Buy feature updates, usage credits and one-off unlocks for your workspace.')"
    >
        <x-slot:actions>
            @if($currentSubscription)
                <div class="boq-current-subscription">
                    <div class="boq-current-subscription-label">{{ __('Current Plan') }}</div>
                    <div class="boq-current-subscription-name">{{ $currentSubscription->plan?->name ?? '—' }}</div>
                </div>
            @else
                <x-ui.button icon="fa-layer-group" :href="route('plans.index')">{{ __('View Plans') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.flash :keys="['message', 'status', 'error']" />

    @if(! $planCode)
        <x-ui.alert type="info" :title="__('Subscribe to a plan before buying top-ups.')">
            {{ __('Top-ups extend an active plan with extra credits and features.') }}
            <a href="{{ route('subscriptions.index', ['tab' => 'plans']) }}" class="ml-1 font-semibold underline-offset-2 hover:underline">{{ __('Choose a plan') }}</a>
        </x-ui.alert>
    @endif

    @if($catalog->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="fa-box-open" :title="__('No top-ups are available right now.')" :description="__('Check back later for credits and feature updates.')" />
        </x-ui.card>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($catalog as $item)
                <article class="boq-plan-card" wire:key="topup-{{ $item['id'] }}">
                    <div class="boq-plan-header">
                        <div class="boq-plan-heading-copy">
                            <span class="boq-plan-main-icon"><i class="fas {{ $typeIcons[$item['type']] ?? 'fa-gift' }}" aria-hidden="true"></i></span>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __(\Illuminate\Support\Str::headline((string) $item['type'])) }}</p>
                            <h2 class="boq-plan-name">{{ $item['name'] }}</h2>
                            @if($item['description'])
                                <p class="boq-plan-description">{{ $item['description'] }}</p>
                            @endif
                        </div>

                        @if($item['owned'])
                            <x-ui.badge color="success" icon="fa-circle-check">{{ __('Owned') }}</x-ui.badge>
                        @endif
                    </div>

                    <div class="boq-plan-price">
                        <span class="boq-plan-price-value text-2xl"><x-money :amount="$item['price']" :currency="$item['currency']" /></span>
                    </div>

                    <div class="boq-plan-duration">
                        <i class="fas fa-clock" aria-hidden="true"></i>
                        {{ $item['is_permanent'] || ! $item['duration_days'] ? __('One-time') : trans_choice(':count day|:count days', (int) $item['duration_days'], ['count' => $item['duration_days']]) }}
                    </div>

                    @if($item['release_version'] || ! empty($item['usage_credits']) || ! empty($item['included_features']))
                        <div class="boq-plan-secondary-limits mb-3">
                            @if($item['release_version'])
                                <div><i class="fas fa-code-branch" aria-hidden="true"></i> v{{ $item['release_version'] }}</div>
                            @endif
                            @foreach(($item['usage_credits'] ?? []) as $key => $value)
                                <div><i class="fas fa-coins" aria-hidden="true"></i> {{ __(\Illuminate\Support\Str::headline((string) $key)) }}: {{ is_numeric($value) ? \App\Support\Format::number($value, 0) : $value }}</div>
                            @endforeach
                            @foreach(array_slice($item['included_features'], 0, 3) as $feature)
                                <div><i class="fas fa-check" aria-hidden="true"></i> {{ $feature }}</div>
                            @endforeach
                        </div>
                    @endif

                    <div class="boq-plan-action">
                        @if($item['purchasable'])
                            <a href="{{ route('checkout', ['type' => 'topup', 'id' => $item['id']]) }}" class="boq-plan-choose-button">
                                <i class="fas fa-cart-shopping" aria-hidden="true"></i> {{ __('Buy') }}
                            </a>
                        @elseif(! $planCode)
                            <span class="boq-current-plan-button border-amber-200 bg-amber-50 text-amber-800">{{ __('Subscribe to a plan first') }}</span>
                        @else
                            <span class="boq-current-plan-button border-slate-200 bg-slate-50 text-slate-500">{{ __('Not available for your plan or purchase limit reached.') }}</span>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    @if($purchases->isNotEmpty())
        <x-ui.card :title="__('Purchase History')" icon="fa-clock-rotate-left" :padded="false">
            <x-ui.table>
                <thead>
                    <tr>
                        <th>{{ __('Top-up') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Purchased') }}</th>
                        <th>{{ __('Expires') }}</th>
                        <th>{{ __('Reference') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($purchases as $purchase)
                        <tr wire:key="purchase-{{ $purchase->id }}">
                            <td class="font-semibold text-slate-900">{{ $purchase->topup?->name ?? __('Deleted top-up') }}</td>
                            <td>
                                <x-ui.badge :color="$purchase->isValid() ? 'success' : 'neutral'" dot>{{ __(\Illuminate\Support\Str::headline((string) $purchase->status)) }}</x-ui.badge>
                            </td>
                            <td class="whitespace-nowrap"><x-date :value="$purchase->purchased_at" /></td>
                            <td class="whitespace-nowrap">
                                @if($purchase->expires_at)
                                    <x-date :value="$purchase->expires_at" />
                                @else
                                    {{ $purchase->is_permanent ? __('Permanent') : '—' }}
                                @endif
                            </td>
                            <td><span class="boq-code">{{ $purchase->transaction?->reference ?? '—' }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        </x-ui.card>
    @endif
</div>
