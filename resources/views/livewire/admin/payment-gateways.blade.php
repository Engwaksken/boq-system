<div class="boq-page-stack">

    <div class="boq-page-header">
        <div>
            <h1 class="boq-page-title"><i class="fas fa-credit-card"></i> Payment Gateways</h1>
            <p class="boq-page-subtitle">Configure direct providers, ioTec Pay and other aggregators without editing JSON.</p>
        </div>
        <button wire:click="create" class="boq-btn-primary"><i class="fas fa-plus"></i> Add Gateway</button>
    </div>

    @include('livewire.admin._tabs')

    @if(session()->has('message'))
        <div class="boq-flash"><i class="fas fa-circle-check"></i> {{ session('message') }}</div>
    @endif

    <div class="boq-stats-grid">
        <div class="boq-stat-card boq-stat-green"><div><p class="boq-stat-label">Gateways</p><p class="boq-stat-value">{{ $stats['gateways'] }}</p></div><span class="boq-stat-icon"><i class="fas fa-credit-card"></i></span></div>
        <div class="boq-stat-card boq-stat-blue"><div><p class="boq-stat-label">Active</p><p class="boq-stat-value">{{ $stats['active'] }}</p></div><span class="boq-stat-icon"><i class="fas fa-circle-check"></i></span></div>
        <div class="boq-stat-card boq-stat-amber"><div><p class="boq-stat-label">Aggregators</p><p class="boq-stat-value">{{ $stats['aggregators'] }}</p></div><span class="boq-stat-icon"><i class="fas fa-diagram-project"></i></span></div>
        <div class="boq-stat-card boq-stat-purple"><div><p class="boq-stat-label">Default Gateway</p><p class="boq-stat-value" style="font-size:1rem">{{ $stats['default'] }}</p></div><span class="boq-stat-icon"><i class="fas fa-star"></i></span></div>
    </div>

    <div class="boq-panel">
        <div class="boq-table-wrapper">
            <table class="boq-table">
                <thead><tr><th>Name</th><th>Code</th><th>Driver</th><th>Status</th><th>Mode</th><th>Default</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                @forelse($gateways as $gateway)
                    <tr wire:key="gateway-{{ $gateway->id }}">
                        <td><div class="boq-table-title">{{ $gateway->name }}</div></td>
                        <td><span class="boq-currency-badge">{{ $gateway->code }}</span></td>
                        <td>{{ $driverOptions[$gateway->driver] ?? $gateway->driver }}</td>
                        <td><span class="boq-badge {{ $gateway->is_active ? 'boq-badge-success' : '' }}">{{ $gateway->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td><span class="boq-badge {{ $gateway->is_test_mode ? 'boq-badge-warning' : 'boq-badge-info' }}">{{ $gateway->is_test_mode ? 'Test' : 'Live' }}</span></td>
                        <td>
                            @if($gateway->is_default)
                                <span class="boq-badge boq-badge-warning"><i class="fas fa-star"></i> Default</span>
                            @else
                                <button wire:click="setDefault({{ $gateway->id }})" class="boq-icon-btn" title="Set Default"><i class="far fa-star"></i></button>
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="boq-table-actions">
                                <button wire:click="edit({{ $gateway->id }})" class="boq-icon-btn" title="Edit"><i class="fas fa-pen"></i></button>
                                <button wire:click="toggleActive({{ $gateway->id }})" class="boq-icon-btn" title="{{ $gateway->is_active ? 'Deactivate' : 'Activate' }}">
                                    <i class="fas {{ $gateway->is_active ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="boq-empty-table"><i class="fas fa-credit-card"></i><span>No payment gateways configured.</span></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($gateways->hasPages())<div class="boq-pagination">{{ $gateways->links() }}</div>@endif
    </div>

    @if($showForm)
        <div class="boq-modal-backdrop" wire:key="gateway-form-modal">
            <div class="boq-modal boq-modal-xl">
                <div class="boq-modal-head">
                    <div>
                        <h2>{{ $editingId ? 'Edit Payment Gateway' : 'Add Payment Gateway' }}</h2>
                        <p class="boq-table-subtitle">Credentials remain encrypted and masked after saving.</p>
                    </div>
                    <button wire:click="cancel" class="boq-modal-close"><i class="fas fa-xmark"></i></button>
                </div>

                <form wire:submit.prevent="save">
                    <div class="boq-modal-body">
                        <div class="boq-form-grid">
                            <div><label class="boq-field-label">Gateway Name</label><input wire:model="form.name" class="boq-field"></div>
                            <div><label class="boq-field-label">Gateway Code</label><input wire:model="form.code" class="boq-field"></div>
                            <div>
                                <label class="boq-field-label">Provider / Driver</label>
                                <select wire:model.live="form.driver" class="boq-field">
                                    @foreach($driverOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                </select>
                            </div>
                            <div><label class="boq-field-label">Timeout (seconds)</label><input type="number" wire:model="form.payment_timeout_seconds" class="boq-field"></div>
                            <div class="boq-form-span-2"><label class="boq-field-label">Description</label><textarea wire:model="form.description" class="boq-field boq-textarea"></textarea></div>
                            <div class="boq-form-span-2"><label class="boq-field-label">Webhook URL</label><input wire:model="form.webhook_url" class="boq-field"></div>
                            <div><label class="boq-field-label">Currencies</label><input wire:model="supportedCurrenciesCsv" class="boq-field"></div>
                            <div><label class="boq-field-label">Countries</label><input wire:model="supportedCountriesCsv" class="boq-field"></div>
                            <div class="boq-form-span-2"><label class="boq-field-label">Payment Methods</label><input wire:model="supportedMethodsCsv" class="boq-field"></div>
                        </div>

                        @if(in_array($form['driver'], ['iotec_pay', 'generic_aggregator'], true))
                            <div class="boq-config-section">
                                <h3><i class="fas fa-network-wired"></i> Aggregator Configuration</h3>

                                <div class="boq-form-grid">
                                    <div><label class="boq-field-label">Provider Name</label><input wire:model="config.provider_name" class="boq-field"></div>
                                    <div><label class="boq-field-label">Base URL</label><input wire:model="config.base_url" class="boq-field"></div>
                                    <div><label class="boq-field-label">Token / Auth URL</label><input wire:model="config.token_url" class="boq-field"></div>
                                    <div><label class="boq-field-label">Collect URL</label><input wire:model="config.collect_url" class="boq-field"></div>
                                    <div><label class="boq-field-label">Status URL</label><input wire:model="config.status_url" class="boq-field"></div>

                                    @if($form['driver'] === 'generic_aggregator')
                                        <div><label class="boq-field-label">Status by Reference URL</label><input wire:model="config.status_by_reference_url" class="boq-field"></div>
                                        <div>
                                            <label class="boq-field-label">Authentication Type</label>
                                            <select wire:model="config.auth_type" class="boq-field">
                                                <option value="oauth2_client_credentials">OAuth2 Client Credentials</option>
                                                <option value="bearer">Bearer Token</option>
                                                <option value="api_key">API Key</option>
                                                <option value="basic">Basic Auth</option>
                                                <option value="none">None</option>
                                            </select>
                                        </div>
                                    @endif

                                    <div><label class="boq-field-label">Client ID</label><input wire:model="config.client_id" class="boq-field"></div>
                                    <div><label class="boq-field-label">Client Secret</label><input type="password" wire:model="config.client_secret" class="boq-field"></div>

                                    @if($form['driver'] === 'iotec_pay')
                                        <div><label class="boq-field-label">Wallet GUID / ID</label><input wire:model="config.wallet_guid" class="boq-field"></div>
                                        <div><label class="boq-field-label">Channel</label><input wire:model="config.channel" class="boq-field"></div>
                                        <div><label class="boq-field-label">Transaction Charges Category</label><input wire:model="config.transaction_charges_category" class="boq-field"></div>
                                    @else
                                        <div><label class="boq-field-label">API Key</label><input type="password" wire:model="config.api_key" class="boq-field"></div>
                                        <div><label class="boq-field-label">Bearer Token</label><input type="password" wire:model="config.bearer_token" class="boq-field"></div>
                                    @endif

                                    <div><label class="boq-field-label">Callback URL</label><input wire:model="config.callback_url" class="boq-field"></div>
                                    <div><label class="boq-field-label">Return URL</label><input wire:model="config.return_url" class="boq-field"></div>
                                    <div><label class="boq-field-label">Currency</label><input wire:model="config.currency" class="boq-field"></div>

                                    <div class="boq-form-span-2 boq-check-row">
                                        <label><input type="checkbox" wire:model="config.supports_collection"> Collection</label>
                                        <label><input type="checkbox" wire:model="config.supports_disbursement"> Disbursement</label>
                                        <label><input type="checkbox" wire:model="config.supports_mtn"> MTN</label>
                                        <label><input type="checkbox" wire:model="config.supports_airtel"> Airtel</label>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="boq-config-section">
                                <h3><i class="fas fa-sliders"></i> Provider Configuration</h3>
                                <div class="boq-form-grid">
                                    @foreach($config as $key => $value)
                                        @if(!is_array($value))
                                            <div>
                                                <label class="boq-field-label">{{ ucwords(str_replace('_', ' ', $key)) }}</label>
                                                <input
                                                    @if(str_contains(strtolower($key), 'secret') || str_contains(strtolower($key), 'key') || str_contains(strtolower($key), 'token')) type="password" @endif
                                                    wire:model="config.{{ $key }}"
                                                    class="boq-field"
                                                >
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="boq-check-row" style="margin-top:1rem">
                            <label><input type="checkbox" wire:model="form.is_active"> Active</label>
                            <label><input type="checkbox" wire:model="form.is_default"> Default Gateway</label>
                            <label><input type="checkbox" wire:model="form.is_test_mode"> Sandbox / Test Mode</label>
                        </div>

                        @if($errors->any())
                            <div class="boq-flash boq-flash-error" style="margin-top:1rem">{{ $errors->first() }}</div>
                        @endif
                    </div>

                    <div class="boq-modal-foot">
                        <button type="button" wire:click="cancel" class="boq-btn-secondary">Cancel</button>
                        <button class="boq-btn-primary"><i class="fas fa-save"></i> Save Gateway</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
