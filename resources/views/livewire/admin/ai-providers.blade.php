<div class="boq-page-stack">

    <div class="boq-page-header">
        <div>
            <h1 class="boq-page-title">
                <i class="fas fa-robot"></i>
                AI API Settings
            </h1>
            <p class="boq-page-subtitle">
                Configure AI providers, models, priorities and fallback behaviour.
            </p>
        </div>

        <button type="button" wire:click="create" class="boq-btn-primary">
            <i class="fas fa-plus"></i>
            Add Provider
        </button>
    </div>

    @include('livewire.admin._tabs')

    @if(session()->has('message'))
        <div class="boq-flash">
            <i class="fas fa-circle-check"></i>
            {{ session('message') }}
        </div>
    @endif

    <div class="boq-stats-grid">
        <div class="boq-stat-card boq-stat-green">
            <div><p class="boq-stat-label">Providers</p><p class="boq-stat-value">{{ $stats['providers'] }}</p></div>
            <span class="boq-stat-icon"><i class="fas fa-robot"></i></span>
        </div>
        <div class="boq-stat-card boq-stat-blue">
            <div><p class="boq-stat-label">Enabled</p><p class="boq-stat-value">{{ $stats['enabled'] }}</p></div>
            <span class="boq-stat-icon"><i class="fas fa-circle-check"></i></span>
        </div>
        <div class="boq-stat-card boq-stat-amber">
            <div><p class="boq-stat-label">Default Provider</p><p class="boq-stat-value" style="font-size:1rem">{{ $stats['default'] }}</p></div>
            <span class="boq-stat-icon"><i class="fas fa-star"></i></span>
        </div>
        <div class="boq-stat-card boq-stat-red">
            <div><p class="boq-stat-label">Failed Connections</p><p class="boq-stat-value">{{ $stats['failed'] }}</p></div>
            <span class="boq-stat-icon"><i class="fas fa-triangle-exclamation"></i></span>
        </div>
    </div>

    <div class="boq-panel">
        <div class="boq-ai-filter-grid">
            <div>
                <label class="boq-field-label">Search</label>
                <div class="boq-input-icon-wrap">
                    <i class="fas fa-search boq-input-icon"></i>
                    <input wire:model.live.debounce.300ms="search" class="boq-field boq-field-with-icon" placeholder="Search provider, key or model...">
                </div>
            </div>
            <div>
                <label class="boq-field-label">Provider Type</label>
                <select wire:model.live="typeFilter" class="boq-field">
                    <option value="all">All types</option>
                    @foreach($providerTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="boq-field-label">Status</label>
                <select wire:model.live="statusFilter" class="boq-field">
                    <option value="all">All statuses</option>
                    <option value="enabled">Enabled</option>
                    <option value="disabled">Disabled</option>
                </select>
            </div>
            <div>
                <label class="boq-field-label">Rows</label>
                <select wire:model.live="perPage" class="boq-field">
                    @foreach([10,25,50,100] as $size)
                        <option value="{{ $size }}">{{ $size }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="boq-panel">
        <div class="boq-table-wrapper">
            <table class="boq-table">
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Type</th>
                        <th>Model</th>
                        <th>Status</th>
                        <th>Default</th>
                        <th>Last Test</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($providers as $provider)
                        <tr wire:key="ai-provider-{{ $provider->id }}">
                            <td>
                                <div class="boq-table-title">{{ $provider->name }}</div>
                                <div class="boq-table-subtitle">{{ $provider->key }}</div>
                            </td>
                            <td>{{ $providerTypes[$provider->provider_type] ?? $provider->provider_type }}</td>
                            <td>{{ $provider->default_model ?: '—' }}</td>
                            <td>
                                <span class="boq-badge {{ $provider->is_enabled ? 'boq-badge-success' : '' }}">
                                    {{ $provider->is_enabled ? 'Enabled' : 'Disabled' }}
                                </span>
                            </td>
                            <td>
                                @if($provider->is_default)
                                    <span class="boq-badge boq-badge-warning"><i class="fas fa-star"></i> Default</span>
                                @else
                                    <button wire:click="setDefault({{ $provider->id }})" class="boq-icon-btn" title="Set Default">
                                        <i class="far fa-star"></i>
                                    </button>
                                @endif
                            </td>
                            <td>
                                @if($provider->last_test_status)
                                    <span class="boq-badge {{ $provider->last_test_status === 'success' ? 'boq-badge-success' : 'boq-badge-danger' }}">
                                        {{ ucfirst($provider->last_test_status) }}
                                    </span>
                                    <div class="boq-table-subtitle">{{ $provider->last_tested_at?->diffForHumans() }}</div>
                                @else
                                    <span class="boq-table-empty">Never tested</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="boq-table-actions">
                                    <button wire:click="testConnection({{ $provider->id }})" class="boq-icon-btn" title="Test Connection">
                                        <i class="fas fa-plug-circle-check"></i>
                                    </button>
                                    <button wire:click="edit({{ $provider->id }})" class="boq-icon-btn" title="Edit">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <button wire:click="toggleEnabled({{ $provider->id }})" class="boq-icon-btn" title="{{ $provider->is_enabled ? 'Disable' : 'Enable' }}">
                                        <i class="fas {{ $provider->is_enabled ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>
                                    </button>
                                    <button wire:click="confirmDelete({{ $provider->id }})" class="boq-icon-btn boq-icon-danger" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="boq-empty-table"><i class="fas fa-robot"></i><span>No AI providers configured.</span></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($providers->hasPages())
            <div class="boq-pagination">{{ $providers->links() }}</div>
        @endif
    </div>

    @if($showForm)
        <div class="boq-modal-backdrop" wire:key="ai-provider-form">
            <div class="boq-modal boq-modal-lg">
                <div class="boq-modal-head">
                    <div>
                        <h2>{{ $editingId ? 'Edit AI Provider' : 'Add AI Provider' }}</h2>
                        <p class="boq-table-subtitle">API keys are encrypted and remain masked after saving.</p>
                    </div>
                    <button wire:click="cancel" class="boq-modal-close"><i class="fas fa-xmark"></i></button>
                </div>

                <form wire:submit.prevent="save">
                    <div class="boq-modal-body">
                        <div class="boq-form-grid">
                            <div>
                                <label class="boq-field-label">Provider Name</label>
                                <input wire:model="form.name" class="boq-field" placeholder="Google Gemini">
                                @error('form.name')<div class="boq-field-error">{{ $message }}</div>@enderror
                            </div>
                            <div>
                                <label class="boq-field-label">Provider Key</label>
                                <input wire:model="form.key" class="boq-field" placeholder="gemini">
                                @error('form.key')<div class="boq-field-error">{{ $message }}</div>@enderror
                            </div>
                            <div>
                                <label class="boq-field-label">Provider Type</label>
                                <select wire:model="form.provider_type" class="boq-field">
                                    @foreach($providerTypes as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="boq-field-label">Default Model</label>
                                <input wire:model="form.default_model" class="boq-field" placeholder="gemini-2.5-flash">
                            </div>
                            <div class="boq-form-span-2">
                                <label class="boq-field-label">API Base URL</label>
                                <input wire:model="form.api_base_url" class="boq-field" placeholder="https://generativelanguage.googleapis.com">
                                @error('form.api_base_url')<div class="boq-field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="boq-form-span-2">
                                <label class="boq-field-label">API Key / Secret</label>
                                <input type="password" wire:model="form.api_key" class="boq-field" autocomplete="new-password">
                                <div class="boq-table-subtitle">Saved credentials display as ***stored*** and are never sent back in plain text.</div>
                            </div>
                            <div>
                                <label class="boq-field-label">Temperature</label>
                                <input type="number" min="0" max="2" step="0.1" wire:model="form.temperature" class="boq-field">
                            </div>
                            <div>
                                <label class="boq-field-label">Timeout (seconds)</label>
                                <input type="number" min="5" max="300" wire:model="form.timeout" class="boq-field">
                            </div>
                            <div>
                                <label class="boq-field-label">Max Tokens</label>
                                <input type="number" wire:model="form.max_tokens" class="boq-field">
                            </div>
                            <div>
                                <label class="boq-field-label">Sort Order</label>
                                <input type="number" min="0" wire:model="form.sort_order" class="boq-field">
                            </div>
                            <div class="boq-form-span-2 boq-check-row">
                                <label><input type="checkbox" wire:model="form.is_enabled"> Enabled</label>
                                <label><input type="checkbox" wire:model="form.is_default"> Default provider</label>
                            </div>
                        </div>

                        @if($errors->any())
                            <div class="boq-flash boq-flash-error" style="margin-top:1rem">{{ $errors->first() }}</div>
                        @endif
                    </div>
                    <div class="boq-modal-foot">
                        <button type="button" wire:click="cancel" class="boq-btn-secondary">Cancel</button>
                        <button class="boq-btn-primary"><i class="fas fa-save"></i> Save Provider</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showDeleteModal)
        <div class="boq-modal-backdrop" wire:key="ai-provider-delete">
            <div class="boq-modal boq-modal-sm">
                <div class="boq-modal-head"><h2>Delete AI provider?</h2></div>
                <div class="boq-modal-body"><p class="boq-modal-message">This provider configuration will be permanently deleted.</p></div>
                <div class="boq-modal-foot">
                    <button wire:click="$set('showDeleteModal', false)" class="boq-btn-secondary">Cancel</button>
                    <button wire:click="delete" class="boq-btn-danger"><i class="fas fa-trash"></i> Delete</button>
                </div>
            </div>
        </div>
    @endif

    @if($showTestModal)
        <div class="boq-modal-backdrop" wire:key="ai-provider-test">
            <div class="boq-modal boq-modal-sm">
                <div class="boq-modal-head"><h2>Connection Test</h2></div>
                <div class="boq-modal-body">
                    @if($testResult)
                        <div class="boq-flash {{ $testResult['ok'] ? '' : 'boq-flash-error' }}">
                            <i class="fas {{ $testResult['ok'] ? 'fa-circle-check' : 'fa-circle-xmark' }}"></i>
                            {{ $testResult['message'] }}
                        </div>
                    @else
                        <div class="boq-loading"><i class="fas fa-spinner fa-spin"></i> Testing connection...</div>
                    @endif
                </div>
                <div class="boq-modal-foot">
                    <button wire:click="closeTestModal" class="boq-btn-secondary">Close</button>
                </div>
            </div>
        </div>
    @endif
</div>
