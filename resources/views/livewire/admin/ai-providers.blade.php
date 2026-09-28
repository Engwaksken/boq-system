<div class="boq-page-stack">
    <x-ui.page-header
        :title="__('AI API Settings')"
        icon="fa-robot"
        :subtitle="__('Configure AI providers, models, priorities and fallback behaviour.')"
    >
        <x-slot:actions>
            <x-ui.button icon="fa-plus" wire:click="create">{{ __('Add Provider') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.flash :keys="['message', 'status', 'error']" />

    <div class="boq-stats-grid">
        <x-stat-card :label="__('Providers')" :value="\App\Support\Format::number($stats['providers'] ?? 0, 0)" icon="fa-robot" color="green" />
        <x-stat-card :label="__('Enabled')" :value="\App\Support\Format::number($stats['enabled'] ?? 0, 0)" icon="fa-circle-check" color="blue" />
        <x-stat-card :label="__('Default Provider')" :value="$stats['default'] ?: '—'" icon="fa-star" color="amber" />
        <x-stat-card :label="__('Failed Connections')" :value="\App\Support\Format::number($stats['failed'] ?? 0, 0)" icon="fa-triangle-exclamation" color="red" />
    </div>

    <div class="boq-panel">
        <div class="boq-ai-filter-grid">
            <x-ui.field :label="__('Search')" for="ai-search">
                <div class="boq-input-icon-wrap">
                    <i class="fas fa-search boq-input-icon" aria-hidden="true"></i>
                    <input id="ai-search" type="search" wire:model.live.debounce.300ms="search" class="boq-field boq-field-with-icon" placeholder="{{ __('Search provider, key or model...') }}">
                </div>
            </x-ui.field>
            <x-ui.field :label="__('Provider Type')" for="ai-type">
                <select id="ai-type" wire:model.live="typeFilter" class="boq-field">
                    <option value="all">{{ __('All types') }}</option>
                    @foreach($providerTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </x-ui.field>
            <x-ui.field :label="__('Status')" for="ai-status">
                <select id="ai-status" wire:model.live="statusFilter" class="boq-field">
                    <option value="all">{{ __('All statuses') }}</option>
                    <option value="enabled">{{ __('Enabled') }}</option>
                    <option value="disabled">{{ __('Disabled') }}</option>
                </select>
            </x-ui.field>
            <x-ui.field :label="__('Rows')" for="ai-rows">
                <select id="ai-rows" wire:model.live="perPage" class="boq-field">
                    @foreach([10, 25, 50, 100] as $size)
                        <option value="{{ $size }}">{{ $size }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </div>
    </div>

    <div class="boq-panel">
        <x-ui.table>
            <thead>
                <tr>
                    <th>{{ __('Provider') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Model') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Default') }}</th>
                    <th>{{ __('Last Test') }}</th>
                    <th class="text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($providers as $provider)
                    <tr wire:key="ai-provider-{{ $provider->id }}">
                        <td>
                            <div class="boq-table-title">{{ $provider->name }}</div>
                            <div class="boq-table-subtitle"><span class="boq-code">{{ $provider->key }}</span></div>
                        </td>
                        <td>{{ $providerTypes[$provider->provider_type] ?? $provider->provider_type }}</td>
                        <td>{{ $provider->default_model ?: '—' }}</td>
                        <td><x-ui.status :status="$provider->is_enabled ? 'enabled' : 'disabled'" /></td>
                        <td>
                            @if($provider->is_default)
                                <x-ui.badge color="warning" icon="fa-star">{{ __('Default') }}</x-ui.badge>
                            @else
                                <button type="button" wire:click="setDefault({{ $provider->id }})" class="boq-icon-btn" title="{{ __('Set Default') }}" aria-label="{{ __('Set Default') }}">
                                    <i class="far fa-star" aria-hidden="true"></i>
                                </button>
                            @endif
                        </td>
                        <td>
                            @if($provider->last_test_status)
                                <x-ui.status :status="$provider->last_test_status" />
                                <div class="boq-table-subtitle">{{ $provider->last_tested_at?->diffForHumans() }}</div>
                            @else
                                <span class="boq-table-empty">{{ __('Never tested') }}</span>
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="boq-table-actions">
                                <button type="button" wire:click="testConnection({{ $provider->id }})" wire:loading.attr="disabled" wire:target="testConnection({{ $provider->id }})" class="boq-icon-btn" title="{{ __('Test Connection') }}" aria-label="{{ __('Test Connection') }}">
                                    <i class="fas fa-plug-circle-check" wire:loading.remove wire:target="testConnection({{ $provider->id }})" aria-hidden="true"></i>
                                    <i class="fas fa-spinner fa-spin" wire:loading wire:target="testConnection({{ $provider->id }})" aria-hidden="true"></i>
                                </button>
                                <button type="button" wire:click="edit({{ $provider->id }})" class="boq-icon-btn" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}">
                                    <i class="fas fa-pen" aria-hidden="true"></i>
                                </button>
                                <button type="button" wire:click="toggleEnabled({{ $provider->id }})" class="boq-icon-btn" title="{{ $provider->is_enabled ? __('Disable') : __('Enable') }}" aria-label="{{ $provider->is_enabled ? __('Disable') : __('Enable') }}">
                                    <i class="fas {{ $provider->is_enabled ? 'fa-toggle-on text-brand-600' : 'fa-toggle-off' }}" aria-hidden="true"></i>
                                </button>
                                <button type="button" wire:click="confirmDelete({{ $provider->id }})" class="boq-icon-btn boq-icon-danger" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}">
                                    <i class="fas fa-trash" aria-hidden="true"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-0">
                            <x-ui.empty-state icon="fa-robot" :title="__('No AI providers configured.')">
                                <x-ui.button size="sm" icon="fa-plus" wire:click="create">{{ __('Add Provider') }}</x-ui.button>
                            </x-ui.empty-state>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>

        @if($providers->hasPages())
            <div class="boq-pagination">{{ $providers->links() }}</div>
        @endif
    </div>

    @if($showForm)
        <x-ui.modal
            wire:key="ai-provider-form"
            id="ai-provider-form"
            :title="$editingId ? __('Edit AI Provider') : __('Add AI Provider')"
            :subtitle="__('API keys are encrypted and remain masked after saving.')"
            icon="fa-robot"
            size="lg"
            close="cancel"
            submit="save"
        >
            <div class="boq-form-grid">
                <x-ui.field :label="__('Provider Name')" for="ai-name" error="form.name" required>
                    <input id="ai-name" wire:model="form.name" class="boq-field @error('form.name') has-error @enderror" placeholder="{{ __('Google Gemini') }}">
                </x-ui.field>
                <x-ui.field :label="__('Provider Key')" for="ai-key" error="form.key" required>
                    <input id="ai-key" wire:model="form.key" class="boq-field @error('form.key') has-error @enderror" placeholder="{{ __('gemini') }}">
                </x-ui.field>
                <x-ui.field :label="__('Provider Type')" for="ai-provider-type" error="form.provider_type">
                    <select id="ai-provider-type" wire:model="form.provider_type" class="boq-field">
                        @foreach($providerTypes as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
                <x-ui.field :label="__('Default Model')" for="ai-model" error="form.default_model">
                    <input id="ai-model" wire:model="form.default_model" class="boq-field" placeholder="{{ __('gemini-2.5-flash') }}">
                </x-ui.field>
                <x-ui.field :label="__('API Base URL')" for="ai-base-url" error="form.api_base_url" class="boq-form-span-2">
                    <input id="ai-base-url" wire:model="form.api_base_url" class="boq-field @error('form.api_base_url') has-error @enderror" placeholder="https://generativelanguage.googleapis.com">
                </x-ui.field>
                <x-ui.field :label="__('API Key / Secret')" for="ai-api-key" error="form.api_key" :hint="__('Saved credentials display as ***stored*** and are never sent back in plain text.')" class="boq-form-span-2">
                    <x-password-input id="ai-api-key" placeholder="••••••••" wire:model="form.api_key" autocomplete="new-password" />
                </x-ui.field>
                <x-ui.field :label="__('Temperature')" for="ai-temperature" error="form.temperature">
                    <input id="ai-temperature" placeholder="0.7" type="number" min="0" max="2" step="0.1" wire:model="form.temperature" class="boq-field">
                </x-ui.field>
                <x-ui.field :label="__('Timeout (seconds)')" for="ai-timeout" error="form.timeout">
                    <input id="ai-timeout" placeholder="30" type="number" min="5" max="300" wire:model="form.timeout" class="boq-field">
                </x-ui.field>
                <x-ui.field :label="__('Max Tokens')" for="ai-max-tokens" error="form.max_tokens">
                    <input id="ai-max-tokens" placeholder="4096" type="number" wire:model="form.max_tokens" class="boq-field">
                </x-ui.field>
                <x-ui.field :label="__('Sort Order')" for="ai-sort" error="form.sort_order">
                    <input id="ai-sort" placeholder="10" type="number" min="0" wire:model="form.sort_order" class="boq-field">
                </x-ui.field>

                <div class="boq-form-span-2 flex flex-wrap items-center gap-x-6 gap-y-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <label class="boq-check"><input type="checkbox" wire:model="form.is_enabled"> {{ __('Enabled') }}</label>
                    <label class="boq-check"><input type="checkbox" wire:model="form.is_default"> {{ __('Default provider') }}</label>
                    <label class="boq-check"><input type="checkbox" wire:model="form.web_search"> {{ __('Live web search (Google grounding)') }}</label>
                    <p class="boq-field-help w-full">{{ __('Enable live web search to fetch real, cited market prices when scanning hardware. Only supported by Google Gemini providers.') }}</p>
                </div>
            </div>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="cancel">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" icon="fa-floppy-disk" loading="save">{{ __('Save Provider') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if($showDeleteModal)
        <x-ui.modal wire:key="ai-provider-delete" id="ai-provider-delete" :title="__('Delete AI provider?')" icon="fa-trash" size="sm" close="$set('showDeleteModal', false)">
            <p class="boq-modal-message">{{ __('This provider configuration will be permanently deleted.') }}</p>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="$set('showDeleteModal', false)">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button variant="danger" icon="fa-trash" wire:click="delete" loading="delete">{{ __('Delete') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if($showTestModal)
        <x-ui.modal wire:key="ai-provider-test" id="ai-provider-test" :title="__('Connection Test')" icon="fa-plug-circle-check" size="sm" close="closeTestModal">
            @if($testResult)
                <x-ui.alert :type="$testResult['ok'] ? 'success' : 'error'">{{ $testResult['message'] }}</x-ui.alert>
            @else
                <div class="boq-loading"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i> {{ __('Testing connection...') }}</div>
            @endif

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="closeTestModal">{{ __('Close') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
