@php
    $backUrl = $type === 'plan' ? route('subscriptions.index') : route('topups.index');
    $dialCode = \App\Models\Country::where('iso2', \App\Support\Regional::countryCode())->value('dial_code') ?: '+1';
@endphp

<div class="boq-page-stack">
    <x-ui.page-header
        :title="__('Checkout')"
        icon="fa-lock"
        :subtitle="$type === 'plan' ? __('Secure payment for your subscription.') : __('Secure payment for your top-up.')"
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="fa-arrow-left" :href="$backUrl">{{ __('Back') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.flash :keys="['message', 'status', 'error']" />

    <div class="boq-checkout-grid">
        <aside class="boq-card self-start lg:sticky lg:top-20">
            <div class="boq-card-header">
                <h2 class="boq-card-title"><i class="fas fa-receipt" aria-hidden="true"></i> {{ __('Order Summary') }}</h2>
            </div>
            <div class="boq-card-body">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">{{ __('Item') }}</dt>
                        <dd class="text-right font-semibold text-slate-900">{{ $summary['name'] }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">{{ __('Details') }}</dt>
                        <dd class="text-right text-slate-700">{{ ucfirst((string) $summary['detail']) }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3 border-t border-slate-200 pt-3">
                        <dt class="font-semibold text-slate-700">{{ __('Total') }}</dt>
                        <dd class="text-xl font-bold tracking-tight text-slate-900"><x-money :amount="$summary['amount']" :currency="$summary['currency']" /></dd>
                    </div>
                </dl>
            </div>
            <div class="boq-card-footer justify-start">
                <p class="flex items-start gap-2 text-xs text-slate-500">
                    <i class="fas fa-shield-halved mt-0.5 text-brand-600" aria-hidden="true"></i>
                    <span>{{ __('Card details are entered on the payment provider\'s secure page and never stored by us.') }}</span>
                </p>
            </div>
        </aside>

        <section class="boq-card">
            <div class="boq-card-body">
                @if($transaction && in_array($transaction->status, ['pending', 'initiated'], true))
                    <div wire:poll.5s="checkStatus" class="py-8 text-center" role="status" aria-live="polite">
                        <span class="boq-empty-icon"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i></span>
                        <h2 class="text-lg font-bold text-slate-900">{{ __('Waiting for payment confirmation') }}</h2>
                        <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">
                            @if(filled(data_get($transaction->metadata, 'customer_input.phone_number')) && ! filled(data_get($transaction->metadata, 'initiation.checkout_url')))
                                {{ __('Approve the payment prompt sent to :phone.', ['phone' => data_get($transaction->metadata, 'customer_input.phone_number')]) }}
                            @else
                                {{ __('Complete the payment in the window that opened. This page updates automatically.') }}
                            @endif
                        </p>
                        <p class="mt-2 text-xs text-slate-400">{{ __('Reference') }} <span class="boq-code">{{ $transaction->reference }}</span></p>

                        <div class="mt-5 flex flex-wrap justify-center gap-2">
                            <x-ui.button variant="secondary" icon="fa-rotate" wire:click="checkStatus" loading="checkStatus">{{ __('Check now') }}</x-ui.button>
                            <x-ui.button variant="ghost" wire:click="startOver">{{ __('Use another method') }}</x-ui.button>
                        </div>
                    </div>
                @elseif($transaction && in_array($transaction->status, ['failed', 'cancelled'], true))
                    <div class="py-8 text-center" role="alert">
                        <span class="boq-empty-icon bg-red-50 text-red-600"><i class="fas fa-circle-xmark" aria-hidden="true"></i></span>
                        <h2 class="text-lg font-bold text-slate-900">{{ __('Payment was not completed') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ $transaction->failure_reason ?: __('The payment failed or was cancelled.') }}</p>
                        <x-ui.button class="mt-5" icon="fa-rotate-left" wire:click="startOver">{{ __('Try again') }}</x-ui.button>
                    </div>
                @else
                    <form wire:submit="pay" class="space-y-5">
                        <fieldset>
                            <legend class="boq-section-title mb-3"><i class="fas fa-credit-card" aria-hidden="true"></i> {{ __('Payment Method') }}</legend>

                            @if($gateways->isEmpty())
                                <x-ui.alert type="warning">
                                    {{ __('No payment method accepts :currency yet. Please contact support.', ['currency' => $summary['currency']]) }}
                                </x-ui.alert>
                            @else
                                <div class="grid gap-2 sm:grid-cols-2" role="radiogroup" aria-label="{{ __('Payment method') }}">
                                    @foreach($gateways as $gateway)
                                        <label class="boq-radio-card" wire:key="gw-{{ $gateway->id }}">
                                            <input type="radio" value="{{ $gateway->code }}" wire:model.live="gatewayCode">
                                            <span>
                                                <span class="block text-sm font-semibold text-slate-800">{{ $gateway->name }}</span>
                                                <span class="block text-xs text-slate-500">{{ $gateway->description ?: ucwords(str_replace('_', ' ', $gateway->driver)) }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif

                            @error('gatewayCode')
                                <p class="boq-field-error" role="alert"><i class="fas fa-circle-exclamation mt-0.5" aria-hidden="true"></i> <span>{{ $message }}</span></p>
                            @enderror
                        </fieldset>

                        @if(count($methods) > 1)
                            <x-ui.field :label="__('Pay With')" for="payment-method" error="paymentMethod">
                                <select id="payment-method" wire:model.live="paymentMethod" class="boq-field">
                                    <option value="">{{ __('Default') }}</option>
                                    @foreach($methods as $method)
                                        <option value="{{ $method }}">{{ ucwords(str_replace('_', ' ', $method)) }}</option>
                                    @endforeach
                                </select>
                            </x-ui.field>
                        @endif

                        @if($needsPhone)
                            <div class="boq-form-grid">
                                <x-ui.field :label="__('Mobile Money Number')" for="checkout-phone" error="phone" required :hint="__('Include the country code.')">
                                    <input id="checkout-phone" type="tel" wire:model="phone" class="boq-field @error('phone') has-error @enderror" placeholder="{{ $dialCode }} 700 000 000" autocomplete="tel" inputmode="tel">
                                </x-ui.field>

                                <x-ui.field :label="__('Network')" for="checkout-network" error="network">
                                    <input id="checkout-network" type="text" wire:model="network" class="boq-field @error('network') has-error @enderror" placeholder="{{ __('e.g. MTN, Airtel, M-Pesa') }}">
                                </x-ui.field>
                            </div>
                        @endif

                        <button type="submit" class="boq-btn-primary boq-btn-lg boq-btn-block" wire:loading.attr="disabled" wire:target="pay" @disabled($gateways->isEmpty())>
                            <i wire:loading.remove wire:target="pay" class="fas fa-lock" aria-hidden="true"></i>
                            <i wire:loading wire:target="pay" class="fas fa-spinner fa-spin" aria-hidden="true"></i>
                            {{ __('Pay') }} <x-money :amount="$summary['amount']" :currency="$summary['currency']" />
                        </button>
                    </form>
                @endif
            </div>
        </section>
    </div>
</div>
