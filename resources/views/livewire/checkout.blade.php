<div class="boq-page-stack">
    <div class="boq-page-header">
        <div>
            <h1 class="boq-page-title"><i class="fas fa-lock"></i> Checkout</h1>
            <p class="boq-page-subtitle">Secure payment for your {{ $type === 'plan' ? 'subscription' : 'top-up' }}.</p>
        </div>
        <a href="{{ $type === 'plan' ? route('subscriptions.index') : route('topups.index') }}" class="boq-btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <div class="boq-checkout-grid">
        {{-- Order summary --}}
        <aside class="boq-panel boq-panel-body">
            <h2 class="boq-section-title mb-3"><i class="fas fa-receipt"></i> Order Summary</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Item</dt><dd class="font-semibold text-slate-900">{{ $summary['name'] }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Details</dt><dd class="text-slate-700">{{ ucfirst($summary['detail']) }}</dd></div>
                <div class="flex justify-between gap-3 border-t border-slate-200 pt-2"><dt class="font-semibold text-slate-700">Total</dt><dd class="text-lg font-extrabold text-slate-900"><x-money :amount="$summary['amount']" :currency="$summary['currency']" /></dd></div>
            </dl>
        </aside>

        {{-- Payment --}}
        <section class="boq-panel boq-panel-body">
            @if($transaction && in_array($transaction->status, ['pending', 'initiated'], true))
                <div wire:poll.5s="checkStatus" class="py-6 text-center">
                    <span class="boq-stat-icon mx-auto mb-3"><i class="fas fa-spinner fa-spin"></i></span>
                    <h2 class="text-lg font-bold text-slate-900">Waiting for payment confirmation</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        @if(filled(data_get($transaction->metadata, 'customer_input.phone_number')) && ! filled(data_get($transaction->metadata, 'initiation.checkout_url')))
                            Approve the payment prompt sent to {{ data_get($transaction->metadata, 'customer_input.phone_number') }}.
                        @else
                            Complete the payment in the window that opened. This page updates automatically.
                        @endif
                    </p>
                    <p class="mt-2 text-xs text-slate-400">Reference {{ $transaction->reference }}</p>

                    <div class="mt-4 flex justify-center gap-2">
                        <button type="button" wire:click="checkStatus" class="boq-btn-secondary"><i class="fas fa-rotate"></i> Check now</button>
                        <button type="button" wire:click="startOver" class="boq-btn-secondary">Use another method</button>
                    </div>
                </div>
            @elseif($transaction && in_array($transaction->status, ['failed', 'cancelled'], true))
                <div class="py-6 text-center">
                    <span class="boq-stat-icon mx-auto mb-3 text-red-600"><i class="fas fa-circle-xmark"></i></span>
                    <h2 class="text-lg font-bold text-slate-900">Payment was not completed</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $transaction->failure_reason ?: 'The payment failed or was cancelled.' }}</p>
                    <button type="button" wire:click="startOver" class="boq-btn-primary mt-4"><i class="fas fa-rotate-left"></i> Try again</button>
                </div>
            @else
                <form wire:submit="pay" class="space-y-5">
                    <div>
                        <h2 class="boq-section-title mb-3"><i class="fas fa-credit-card"></i> Payment Method</h2>

                        @if($gateways->isEmpty())
                            <div class="boq-flash boq-flash-warning">
                                <i class="fas fa-triangle-exclamation"></i>
                                No payment method accepts {{ $summary['currency'] }} yet. Please contact support.
                            </div>
                        @else
                            <div class="grid gap-2 sm:grid-cols-2" role="radiogroup" aria-label="Payment method">
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
                        @error('gatewayCode') <p class="boq-field-error">{{ $message }}</p> @enderror
                    </div>

                    @if(count($methods) > 1)
                        <div>
                            <label for="payment-method" class="boq-field-label">Pay With</label>
                            <select id="payment-method" wire:model.live="paymentMethod" class="boq-field">
                                <option value="">Default</option>
                                @foreach($methods as $method)
                                    <option value="{{ $method }}">{{ ucwords(str_replace('_', ' ', $method)) }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if($needsPhone)
                        <div class="boq-form-grid">
                            <div>
                                <label for="checkout-phone" class="boq-field-label">Mobile Money Number *</label>
                                <input id="checkout-phone" type="tel" wire:model="phone" class="boq-field" placeholder="{{ (\App\Models\Country::where('iso2', \App\Support\Regional::countryCode())->value('dial_code') ?: '+1') }} 700 000 000 (include country code)" autocomplete="tel">
                                @error('phone') <p class="boq-field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="checkout-network" class="boq-field-label">Network</label>
                                <input id="checkout-network" type="text" wire:model="network" class="boq-field" placeholder="e.g. MTN, Airtel, M-Pesa">
                                @error('network') <p class="boq-field-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    @endif

                    <button type="submit" class="boq-btn-primary w-full justify-center" wire:loading.attr="disabled" wire:target="pay" @disabled($gateways->isEmpty())>
                        <i wire:loading.remove wire:target="pay" class="fas fa-lock"></i>
                        <i wire:loading wire:target="pay" class="fas fa-spinner fa-spin"></i>
                        Pay <x-money :amount="$summary['amount']" :currency="$summary['currency']" />
                    </button>

                    <p class="text-center text-xs text-slate-400">
                        <i class="fas fa-shield-halved"></i>
                        Card details are entered on the payment provider's secure page and never stored by us.
                    </p>
                </form>
            @endif
        </section>
    </div>
</div>
