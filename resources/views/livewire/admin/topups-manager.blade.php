<div class="boq-page-stack" x-data="{ confirmArchive: false, archiveId: null, archiveName: '' }">
    <x-ui.page-header
        :title="__('Top-ups & Purchases')"
        icon="fa-gift"
        :subtitle="__('Feature updates, usage credits and one-off unlocks users can buy.')"
    >
        <x-slot:actions>
            <x-ui.button icon="fa-plus" wire:click="create">{{ __('Add Top-up') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.flash :keys="['message', 'status', 'error']" />

    <div class="boq-panel">
        <div class="boq-toolbar border-b border-slate-200">
            <div class="boq-input-icon-wrap boq-toolbar-grow">
                <i class="fas fa-magnifying-glass boq-input-icon" aria-hidden="true"></i>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search top-ups...') }}" aria-label="{{ __('Search top-ups...') }}" class="boq-field boq-field-with-icon">
            </div>
        </div>

        <x-bulk-bar :count="count($selected)">
            <button type="button" wire:click="bulkSetActive(true)" class="boq-btn-secondary"><i class="fas fa-circle-check" aria-hidden="true"></i> {{ __('Activate') }}</button>
            <button type="button" wire:click="bulkSetActive(false)" class="boq-btn-secondary"><i class="fas fa-ban" aria-hidden="true"></i> {{ __('Deactivate') }}</button>
            <button type="button" wire:click="bulkArchive" wire:confirm="{{ __('Archive the selected top-ups? Existing purchases stay active.') }}" class="boq-btn-danger"><i class="fas fa-box-archive" aria-hidden="true"></i> {{ __('Archive') }}</button>
            <button type="button" wire:click="bulkDelete" wire:confirm="{{ __('Delete the selected top-ups? Top-ups that were purchased are skipped.') }}" class="boq-btn-danger"><i class="fas fa-trash" aria-hidden="true"></i> {{ __('Delete') }}</button>
        </x-bulk-bar>

        <x-ui.table>
            <thead>
                <tr>
                    <th class="boq-check-col"><x-select-all :ids="$topups->pluck('id')" :selected="$selected" /></th>
                    <th>{{ __('Top-up') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th class="text-right">{{ __('Price') }}</th>
                    <th>{{ __('Credits') }}</th>
                    <th>{{ __('Availability') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($topups as $topup)
                    <tr wire:key="topup-{{ $topup->id }}">
                        <td class="boq-check-col"><x-select-row :id="$topup->id" /></td>
                        <td>
                            <div class="boq-table-title">{{ $topup->name }}</div>
                            <div class="boq-table-subtitle"><span class="boq-code">{{ $topup->code }}</span>@if($topup->release_version) · v{{ $topup->release_version }}@endif</div>
                        </td>
                        <td><x-ui.badge>{{ __(\Illuminate\Support\Str::headline((string) $topup->type)) }}</x-ui.badge></td>
                        <td class="is-numeric font-semibold text-slate-900"><x-money :amount="$topup->price" :currency="$topup->currency" /></td>
                        <td>
                            @if($topup->isUsageTopup())
                                <div class="flex flex-wrap gap-1">
                                    @foreach(($topup->usage_credits ?? []) as $key => $value)
                                        <x-ui.badge color="brand">{{ \Illuminate\Support\Str::headline((string) $key) }}: {{ $value }}</x-ui.badge>
                                    @endforeach
                                </div>
                            @elseif(count($topup->included_features ?? []))
                                <span class="text-xs text-slate-500">{{ trans_choice(':count feature|:count features', count($topup->included_features), ['count' => count($topup->included_features)]) }}</span>
                            @else
                                <span class="boq-table-empty">—</span>
                            @endif
                        </td>
                        <td>
                            <div>{{ $topup->duration_days ? trans_choice(':count day|:count days', (int) $topup->duration_days, ['count' => $topup->duration_days]) : ($topup->is_permanent ? __('Lifetime') : __('Unlimited')) }}</div>
                            <div class="boq-table-meta">{{ trans_choice(':count purchase|:count purchases', $purchaseCounts[$topup->id] ?? 0, ['count' => $purchaseCounts[$topup->id] ?? 0]) }}</div>
                        </td>
                        <td>
                            @if($topup->is_archived)
                                <x-ui.badge color="danger" dot>{{ __('Archived') }}</x-ui.badge>
                            @else
                                <x-ui.status :status="$topup->is_active ? 'active' : 'inactive'" />
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="boq-table-actions">
                                <button type="button" wire:click="edit({{ $topup->id }})" class="boq-icon-btn" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}"><i class="fas fa-pen" aria-hidden="true"></i></button>
                                <button type="button" wire:click="toggleActive({{ $topup->id }})" class="boq-icon-btn" title="{{ $topup->is_active ? __('Deactivate') : __('Activate') }}" aria-label="{{ $topup->is_active ? __('Deactivate') : __('Activate') }}"><i class="fas {{ $topup->is_active ? 'fa-ban' : 'fa-circle-check' }}" aria-hidden="true"></i></button>
                                @unless($topup->is_archived)
                                    <button type="button" @click="archiveId={{ $topup->id }}; archiveName=@js($topup->name); confirmArchive=true" class="boq-icon-btn boq-icon-danger" title="{{ __('Archive') }}" aria-label="{{ __('Archive') }}"><i class="fas fa-box-archive" aria-hidden="true"></i></button>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="p-0"><x-ui.empty-state icon="fa-gift" :title="__('No top-ups found.')" /></td></tr>
                @endforelse
            </tbody>
        </x-ui.table>

        @if($topups->hasPages())<div class="boq-pagination">{{ $topups->links() }}</div>@endif
    </div>

    @if($showForm)
        <x-ui.modal
            wire:key="topup-form-modal"
            id="topup-form"
            :title="$editingId ? __('Edit Top-up') : __('Create Top-up')"
            :subtitle="__('Changes are saved without leaving this page.')"
            icon="fa-gift"
            size="lg"
            close="cancel"
            submit="save"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <x-ui.field :label="__('Name')" for="topup-name" error="form.name" required>
                    <input id="topup-name" placeholder="{{ __('Enter name') }}" wire:model="form.name" class="boq-field @error('form.name') has-error @enderror">
                </x-ui.field>
                <x-ui.field :label="__('Code')" for="topup-code" error="form.code">
                    <input id="topup-code" wire:model="form.code" class="boq-field @error('form.code') has-error @enderror" placeholder="{{ __('auto-generated') }}">
                </x-ui.field>
                <x-ui.field :label="__('Type')" for="topup-type" error="form.type" required>
                    <select id="topup-type" wire:model="form.type" class="boq-field">
                        <option value="feature_unlock">{{ __('Feature Unlock') }}</option>
                        <option value="version_update">{{ __('Version Update') }}</option>
                        <option value="bundle">{{ __('Bundle') }}</option>
                        <option value="ai_credit_topup">{{ __('AI Credit Top-up') }}</option>
                        <option value="ocr_credit_topup">{{ __('OCR Credit Top-up') }}</option>
                        <option value="translation_credit_topup">{{ __('Translation Credit Top-up') }}</option>
                        <option value="storage_topup">{{ __('Storage Top-up') }}</option>
                        <option value="user_seat_topup">{{ __('User Seat Top-up') }}</option>
                        <option value="project_limit_topup">{{ __('Project Limit Top-up') }}</option>
                        <option value="boq_limit_topup">{{ __('BOQ Limit Top-up') }}</option>
                        <option value="report_export_topup">{{ __('Report Export Top-up') }}</option>
                    </select>
                </x-ui.field>

                <x-ui.field :label="__('Description')" for="topup-description" error="form.description" class="md:col-span-3">
                    <textarea id="topup-description" placeholder="{{ __('Add description...') }}" wire:model="form.description" rows="2" class="boq-field"></textarea>
                </x-ui.field>

                <x-ui.field :label="__('Currency')" for="topup-currency" error="form.currency" required>
                    <x-currency-select id="topup-currency" wire:model="form.currency" :current="$form['currency'] ?? null" />
                </x-ui.field>

                @foreach(['price' => __('Price'), 'duration_days' => __('Duration Days'), 'release_version' => __('Release Version'), 'purchase_limit' => __('Purchase Limit'), 'display_order' => __('Display Order')] as $field => $label)
                    <x-ui.field :label="$label" for="topup-{{ $field }}" :error="'form.'.$field">
                        <input id="topup-{{ $field }}" wire:model="form.{{ $field }}" class="boq-field @error('form.'.$field) has-error @enderror" type="{{ in_array($field, ['duration_days', 'purchase_limit', 'display_order'], true) ? 'number' : 'text' }}" @if($field === 'price') inputmode="decimal" @endif>
                    </x-ui.field>
                @endforeach

                <x-ui.field :label="__('Included features')" for="topup-features" error="form.included_features" :hint="__('Comma separated feature codes.')" class="md:col-span-3">
                    <input id="topup-features" wire:model="form.included_features" class="boq-field" placeholder="variations,cost.tracking">
                </x-ui.field>

                <x-ui.field :label="__('Usage credits')" for="topup-credits" error="form.usage_credits" :hint="__('JSON object mapping each credit type to an amount.')" class="md:col-span-3">
                    <textarea id="topup-credits" wire:model="form.usage_credits" rows="2" class="boq-field font-mono text-xs" placeholder='{"ai_credits":50}'></textarea>
                </x-ui.field>

                <x-ui.field :label="__('Applicable plans')" for="topup-plans" error="form.applicable_plans" :hint="__('Comma separated plan codes; leave blank for all plans.')" class="md:col-span-3">
                    <input id="topup-plans" wire:model="form.applicable_plans" class="boq-field" placeholder="monthly-professional,one-time">
                </x-ui.field>

                <div class="flex flex-wrap items-center gap-x-6 gap-y-3 rounded-lg border border-slate-200 bg-slate-50 p-3 md:col-span-3">
                    <label class="boq-check"><input type="checkbox" wire:model="form.is_active"> {{ __('Active') }}</label>
                    <label class="boq-check"><input type="checkbox" wire:model="form.is_permanent"> {{ __('Permanent (no expiry)') }}</label>
                    <label class="boq-check"><input type="checkbox" wire:model="form.requires_confirmation"> {{ __('Requires payment confirmation') }}</label>
                </div>
            </div>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="cancel">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" icon="fa-floppy-disk" loading="save">{{ __('Save Top-up') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    <div x-show="confirmArchive" x-cloak class="boq-modal-backdrop" role="dialog" aria-modal="true" @keydown.escape.window="confirmArchive=false">
        <div class="boq-modal boq-modal-sm" @click.outside="confirmArchive=false">
            <div class="boq-modal-head">
                <h2><i class="fas fa-box-archive" aria-hidden="true"></i> {{ __('Archive top-up?') }}</h2>
                <button type="button" @click="confirmArchive=false" class="boq-modal-close" aria-label="{{ __('Close') }}"><i class="fas fa-xmark" aria-hidden="true"></i></button>
            </div>
            <div class="boq-modal-body boq-modal-message">{{ __('Archive') }} <strong x-text="archiveName"></strong>{{ __('? Existing purchases will remain valid until they expire.') }}</div>
            <div class="boq-modal-foot">
                <button type="button" @click="confirmArchive=false" class="boq-btn-secondary">{{ __('Cancel') }}</button>
                <button type="button" @click="$wire.archive(archiveId); confirmArchive=false" class="boq-btn-danger">{{ __('Archive') }}</button>
            </div>
        </div>
    </div>
</div>
