<div class="boq-page-stack">
    <div class="boq-page-header">
        <div>
            <h1 class="boq-page-title">
                <i class="fas fa-credit-card"></i>
                {{ __('Payment Gateways') }}
            </h1>
            <p class="boq-page-subtitle">{{ __('Configure payment drivers, API credentials and webhook settings.') }}</p>
        </div>

        <button type="button" wire:click="create" class="boq-btn-primary">
            <i class="fas fa-plus"></i>
            {{ __('Add Gateway') }}
        </button>
    </div>

    @if (session()->has('message'))
        <div class="boq-flash">
            <i class="fas fa-circle-check"></i>
            {{ session('message') }}
        </div>
    @endif

    <div class="boq-stats-grid">
        <x-stat-card label="Gateways" :value="\App\Support\Format::number($stats['gateways'], 0)" icon="fa-credit-card" color="green" />
        <x-stat-card label="Active" :value="\App\Support\Format::number($stats['active'], 0)" icon="fa-circle-check" color="blue" />
        <x-stat-card label="Aggregators" :value="\App\Support\Format::number($stats['aggregators'], 0)" icon="fa-network-wired" color="purple" />
        <x-stat-card label="Default Gateway" :value="$stats['default']" icon="fa-star" color="amber" />
    </div>

    <div class="boq-panel overflow-hidden">
        <x-bulk-bar :count="count($selected)">
            <button type="button" wire:click="bulkSetActive(true)" class="boq-btn-secondary">
                <i class="fas fa-circle-check"></i> {{ __('Activate') }}
            </button>
            <button type="button" wire:click="bulkSetActive(false)" wire:confirm="Deactivate the selected gateways?" class="boq-btn-secondary">
            <button type="button" wire:click="bulkDelete" wire:confirm="Delete the selected gateways? Gateways with payments and the default gateway are skipped." class="boq-btn-danger"><i class="fas fa-trash"></i> {{ __('Delete') }}</button>
                <i class="fas fa-ban"></i> {{ __('Deactivate') }}
            </button>
        </x-bulk-bar>

        <div class="boq-table-wrapper">
            <table class="boq-table">
                <thead>
                    <tr>
                        <th class="boq-check-col">
                            <x-select-all :ids="$gateways->pluck('id')" :selected="$selected" />
                        </th>
                        <th>{{ __('Gateway') }}</th>
                        <th>{{ __('Driver') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Mode') }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($gateways as $gateway)
                        <tr wire:key="gateway-{{ $gateway->id }}">
                            <td class="boq-check-col">
                                <x-select-row :id="$gateway->id" />
                            </td>
                            <td>
                                <div class="boq-table-title">
                                    {{ $gateway->name }}
                                    @if($gateway->is_default)
                                        <span class="boq-badge boq-badge-warning ml-1"><i class="fas fa-star"></i> {{ __('Default') }}</span>
                                    @endif
                                </div>
                                <div class="boq-table-subtitle font-mono">{{ $gateway->code }}</div>
                            </td>
                            <td>{{ $driverOptions[$gateway->driver] ?? $gateway->driver }}</td>
                            <td>
                                <span class="boq-badge {{ $gateway->is_active ? 'boq-badge-success' : 'boq-badge-danger' }}">
                                    {{ $gateway->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <span class="boq-badge {{ $gateway->is_test_mode ? 'boq-badge-warning' : 'boq-badge-info' }}">
                                    {{ $gateway->is_test_mode ? 'Test' : 'Live' }}
                                </span>
                            </td>
                            <td>
                                <div class="boq-table-actions justify-end">
                                    <button type="button" wire:click="edit({{ $gateway->id }})" class="boq-icon-btn" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    @unless($gateway->is_default)
                                        <button type="button" wire:click="setDefault({{ $gateway->id }})" class="boq-icon-btn" title="{{ __('Make default') }}" aria-label="{{ __('Make default') }}">
                                            <i class="far fa-star"></i>
                                        </button>
                                    @endunless
                                    <button type="button" wire:click="toggleActive({{ $gateway->id }})" class="boq-icon-btn {{ $gateway->is_active ? 'boq-icon-danger' : '' }}" title="{{ $gateway->is_active ? 'Deactivate' : 'Activate' }}" aria-label="{{ $gateway->is_active ? 'Deactivate' : 'Activate' }}">
                                        <i class="fas {{ $gateway->is_active ? 'fa-ban' : 'fa-circle-check' }}"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="boq-table-empty">{{ __('No payment gateways configured.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($gateways->hasPages())
            <div class="boq-pagination">{{ $gateways->links() }}</div>
        @endif
    </div>

    @if($showForm)
        <div class="boq-modal-backdrop" wire:key="gateway-modal" role="dialog" aria-modal="true" aria-labelledby="gateway-modal-title">
            <form wire:submit="save" class="boq-modal boq-modal-lg">
                <div class="boq-modal-head">
                    <h2 id="gateway-modal-title">
                        <i class="fas {{ $editingId ? 'fa-pen' : 'fa-plus' }}"></i>
                        {{ $editingId ? 'Edit Payment Gateway' : 'New Payment Gateway' }}
                    </h2>
                    <button type="button" wire:click="cancel" class="boq-modal-close" aria-label="{{ __('Close') }}">
                        <i class="fas fa-xmark"></i>
                    </button>
                </div>

                <div class="boq-modal-body space-y-5">
                    <div class="boq-form-grid">
                        <div>
                            <label for="gw-name" class="boq-field-label">{{ __('Name *') }}</label>
                            <input id="gw-name" type="text" wire:model="form.name" class="boq-field" placeholder="{{ __('e.g. ioTec Pay') }}">
                            @error('form.name') <p class="boq-field-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="gw-code" class="boq-field-label">Code (unique) *</label>
                            <input id="gw-code" type="text" wire:model="form.code" class="boq-field" placeholder="{{ __('e.g. iotec') }}">
                            @error('form.code') <p class="boq-field-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="gw-driver" class="boq-field-label">{{ __('Driver *') }}</label>
                            <select id="gw-driver" wire:model.live="form.driver" class="boq-field">
                                @foreach($driverOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="boq-field-help">{{ __('Changing the driver loads its configuration template.') }}</p>
                            @error('form.driver') <p class="boq-field-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="gw-timeout" class="boq-field-label">Payment Timeout (seconds) *</label>
                            <input id="gw-timeout" type="number" min="30" wire:model="form.payment_timeout_seconds" class="boq-field" placeholder="900">
                            @error('form.payment_timeout_seconds') <p class="boq-field-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="boq-form-span-2">
                            <label for="gw-description" class="boq-field-label">{{ __('Description') }}</label>
                            <textarea id="gw-description" wire:model="form.description" rows="2" class="boq-field boq-textarea" placeholder="{{ __('Shown to administrators only') }}"></textarea>
                            @error('form.description') <p class="boq-field-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="boq-form-span-2">
                            <label for="gw-webhook" class="boq-field-label">{{ __('Webhook URL') }}</label>
                            <input id="gw-webhook" type="url" wire:model="form.webhook_url" class="boq-field" placeholder="{{ url('/api/v1/payments/webhook') }}">
                            @error('form.webhook_url') <p class="boq-field-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="gw-currencies" class="boq-field-label">{{ __('Supported Currencies') }}</label>
                            <input id="gw-currencies" type="text" wire:model="supportedCurrenciesCsv" class="boq-field" placeholder="{{ __('UGX, USD') }}">
                            @error('supportedCurrenciesCsv') <p class="boq-field-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="gw-countries" class="boq-field-label">{{ __('Supported Countries') }}</label>
                            <input id="gw-countries" type="text" wire:model="supportedCountriesCsv" class="boq-field" placeholder="{{ __('UG') }}">
                            @error('supportedCountriesCsv') <p class="boq-field-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="boq-form-span-2">
                            <label for="gw-methods" class="boq-field-label">{{ __('Supported Methods') }}</label>
                            <input id="gw-methods" type="text" wire:model="supportedMethodsCsv" class="boq-field" placeholder="{{ __('mobile_money, card') }}">
                            <p class="boq-field-help">{{ __('Comma separated.') }}</p>
                            @error('supportedMethodsCsv') <p class="boq-field-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="boq-form-span-2 flex flex-wrap gap-5">
                            <label class="boq-check">
                                <input type="checkbox" wire:model="form.is_active">
                                <span>{{ __('Active') }}</span>
                            </label>
                            <label class="boq-check">
                                <input type="checkbox" wire:model="form.is_test_mode">
                                <span>{{ __('Test mode') }}</span>
                            </label>
                            <label class="boq-check">
                                <input type="checkbox" wire:model="form.is_default">
                                <span>{{ __('Default gateway') }}</span>
                            </label>
                        </div>
                    </div>

                    @if($config !== [])
                        <div>
                            <h3 class="boq-section-title">
                                <i class="fas fa-key"></i>
                                {{ __('Driver Configuration') }}
                            </h3>
                            <p class="boq-field-help mb-3">{{ __('Saved secrets show as') }} <code>{{ __('***stored***') }}</code>; leave them unchanged to keep the stored value.</p>

                            <div class="boq-form-grid">
                                @foreach($config as $key => $value)
                                    @if(is_array($value))
                                        <fieldset class="boq-form-span-2 boq-fieldset">
                                            <legend>{{ \Illuminate\Support\Str::headline($key) }}</legend>
                                            <div class="boq-form-grid">
                                                @foreach($value as $subKey => $subValue)
                                                    @unless(is_array($subValue))
                                                        <div>
                                                            <label for="cfg-{{ $key }}-{{ $subKey }}" class="boq-field-label">{{ \Illuminate\Support\Str::headline($subKey) }}</label>
                                                            <input placeholder="{{ \Illuminate\Support\Str::headline($subKey) }}" id="cfg-{{ $key }}-{{ $subKey }}" type="text" wire:model="config.{{ $key }}.{{ $subKey }}" class="boq-field">
                                                        </div>
                                                    @endunless
                                                @endforeach
                                            </div>
                                        </fieldset>
                                    @elseif(is_bool($value))
                                        <label class="boq-check">
                                            <input type="checkbox" wire:model="config.{{ $key }}">
                                            <span>{{ \Illuminate\Support\Str::headline($key) }}</span>
                                        </label>
                                    @else
                                        @php
                                            $isSecret = \Illuminate\Support\Str::contains(strtolower($key), ['secret', 'key', 'token', 'password']);
                                        @endphp
                                        <div>
                                            <label for="cfg-{{ $key }}" class="boq-field-label">{{ \Illuminate\Support\Str::headline($key) }}</label>
                                            @if($isSecret)
                                                <x-password-input placeholder="••••••••" id="cfg-{{ $key }}" wire:model="config.{{ $key }}" autocomplete="off" />
                                            @else
                                                <input placeholder="{{ \Illuminate\Support\Str::headline($key) }}" id="cfg-{{ $key }}" type="text" wire:model="config.{{ $key }}" class="boq-field" autocomplete="off">
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="boq-modal-foot">
                    <button type="button" wire:click="cancel" class="boq-btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="save" class="boq-btn-primary">
                        <i wire:loading.remove wire:target="save" class="fas fa-floppy-disk"></i>
                        <i wire:loading wire:target="save" class="fas fa-spinner fa-spin"></i>
                        {{ __('Save Gateway') }}
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
