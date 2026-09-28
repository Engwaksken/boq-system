<div class="boq-page-stack" x-data="{ confirmArchive: false, archiveId: null, archiveName: '' }">
    <x-ui.page-header
        :title="__('Subscription Plans')"
        icon="fa-layer-group"
        :subtitle="__('Create and manage pricing, limits and trial eligibility.')"
    >
        <x-slot:actions>
            <x-ui.button icon="fa-plus" wire:click="create">{{ __('Add Plan') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.flash :keys="['message', 'status', 'error']" />

    <div class="boq-stats-grid">
        <x-stat-card :label="__('Total Plans')" :value="\App\Support\Format::number($stats['total'], 0)" icon="fa-layer-group" color="green" />
        <x-stat-card :label="__('Active Plans')" :value="\App\Support\Format::number($stats['active'], 0)" icon="fa-circle-check" color="blue" />
        <x-stat-card :label="__('Trial Enabled')" :value="\App\Support\Format::number($stats['trial'], 0)" icon="fa-hourglass-half" color="amber" />
        <x-stat-card :label="__('Active Subscribers')" :value="\App\Support\Format::number($stats['subscribers'], 0)" icon="fa-users" color="purple" :href="route('admin.subscriptions')" />
    </div>

    <div class="boq-panel">
        <div class="boq-toolbar border-b border-slate-200">
            <div class="boq-input-icon-wrap boq-toolbar-grow">
                <i class="fas fa-magnifying-glass boq-input-icon" aria-hidden="true"></i>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search plans...') }}" aria-label="{{ __('Search plans...') }}" class="boq-field boq-field-with-icon">
            </div>
        </div>

        <x-bulk-bar :count="count($selected)">
            <button type="button" wire:click="bulkSetActive(true)" class="boq-btn-secondary"><i class="fas fa-circle-check" aria-hidden="true"></i> {{ __('Activate') }}</button>
            <button type="button" wire:click="bulkSetActive(false)" class="boq-btn-secondary"><i class="fas fa-ban" aria-hidden="true"></i> {{ __('Deactivate') }}</button>
            <button type="button" wire:click="bulkArchive" wire:confirm="{{ __('Archive the selected plans? Existing subscriptions are kept.') }}" class="boq-btn-danger"><i class="fas fa-box-archive" aria-hidden="true"></i> {{ __('Archive') }}</button>
            <button type="button" wire:click="bulkDelete" wire:confirm="{{ __('Delete the selected plans? Plans with subscriptions are skipped.') }}" class="boq-btn-danger"><i class="fas fa-trash" aria-hidden="true"></i> {{ __('Delete') }}</button>
        </x-bulk-bar>

        <x-ui.table>
            <thead>
                <tr>
                    <th class="boq-check-col"><x-select-all :ids="$plans->pluck('id')" :selected="$selected" /></th>
                    <th>{{ __('Plan') }}</th>
                    <th class="text-right">{{ __('Price') }}</th>
                    <th>{{ __('Duration') }}</th>
                    <th>{{ __('Limits') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($plans as $plan)
                    <tr wire:key="plan-{{ $plan->id }}">
                        <td class="boq-check-col"><x-select-row :id="$plan->id" /></td>
                        <td>
                            <div class="boq-table-title">{{ $plan->name }}</div>
                            <div class="boq-table-subtitle"><span class="boq-code">{{ $plan->code }}</span> · {{ __(\Illuminate\Support\Str::headline((string) $plan->type)) }}</div>
                        </td>
                        <td class="is-numeric font-semibold text-slate-900"><x-money :amount="$plan->price" :currency="$plan->currency" /></td>
                        <td class="whitespace-nowrap">{{ $plan->duration_days ? trans_choice(':count day|:count days', (int) $plan->duration_days, ['count' => $plan->duration_days]) : '—' }}</td>
                        <td>
                            <div class="flex flex-wrap gap-1">
                                <x-ui.badge icon="fa-folder-open">{{ $plan->max_projects ?? '∞' }}</x-ui.badge>
                                <x-ui.badge icon="fa-file-invoice-dollar">{{ $plan->max_boqs ?? '∞' }}</x-ui.badge>
                                <x-ui.badge icon="fa-robot">{{ $plan->max_ai_credits ?? '∞' }}</x-ui.badge>
                            </div>
                        </td>
                        <td>
                            @if($plan->is_archived)
                                <x-ui.badge color="danger" dot>{{ __('Archived') }}</x-ui.badge>
                            @else
                                <x-ui.status :status="$plan->is_active ? 'active' : 'inactive'" />
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="boq-table-actions">
                                <button type="button" wire:click="edit({{ $plan->id }})" class="boq-icon-btn" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}"><i class="fas fa-pen" aria-hidden="true"></i></button>
                                <button type="button" wire:click="toggleActive({{ $plan->id }})" class="boq-icon-btn" title="{{ $plan->is_active ? __('Deactivate') : __('Activate') }}" aria-label="{{ $plan->is_active ? __('Deactivate') : __('Activate') }}"><i class="fas {{ $plan->is_active ? 'fa-ban' : 'fa-circle-check' }}" aria-hidden="true"></i></button>
                                @unless($plan->is_archived)
                                    <button type="button" @click="archiveId={{ $plan->id }}; archiveName=@js($plan->name); confirmArchive=true" class="boq-icon-btn boq-icon-danger" title="{{ __('Archive') }}" aria-label="{{ __('Archive') }}"><i class="fas fa-box-archive" aria-hidden="true"></i></button>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-0"><x-ui.empty-state icon="fa-layer-group" :title="__('No plans found.')" /></td></tr>
                @endforelse
            </tbody>
        </x-ui.table>

        @if($plans->hasPages())<div class="boq-pagination">{{ $plans->links() }}</div>@endif
    </div>

    @if($showForm)
        <x-ui.modal
            wire:key="plan-form-modal"
            id="plan-form"
            :title="$editingId ? __('Edit Plan') : __('Create Plan')"
            :subtitle="__('Changes are saved without leaving this page.')"
            icon="fa-layer-group"
            size="lg"
            close="cancel"
            submit="save"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <x-ui.field :label="__('Name')" for="plan-name" error="form.name" required>
                    <input id="plan-name" placeholder="{{ __('Enter name') }}" wire:model="form.name" class="boq-field @error('form.name') has-error @enderror">
                </x-ui.field>
                <x-ui.field :label="__('Code')" for="plan-code" error="form.code">
                    <input id="plan-code" wire:model="form.code" class="boq-field @error('form.code') has-error @enderror" placeholder="{{ __('auto-generated') }}">
                </x-ui.field>
                <x-ui.field :label="__('Type')" for="plan-type" error="form.type" required>
                    <select id="plan-type" wire:model="form.type" class="boq-field">
                        <option value="monthly">{{ __('Monthly') }}</option>
                        <option value="three_month">{{ __('3 Months') }}</option>
                        <option value="six_month">{{ __('6 Months') }}</option>
                        <option value="annual">{{ __('Annual') }}</option>
                        <option value="one_time">{{ __('One Time') }}</option>
                        <option value="lifetime">{{ __('Lifetime') }}</option>
                    </select>
                </x-ui.field>

                <x-ui.field :label="__('Description')" for="plan-description" error="form.description" class="md:col-span-3">
                    <textarea id="plan-description" placeholder="{{ __('Add description...') }}" wire:model="form.description" rows="2" class="boq-field"></textarea>
                </x-ui.field>

                <x-ui.field :label="__('Currency')" for="plan-currency" error="form.currency" required>
                    <x-currency-select id="plan-currency" wire:model="form.currency" :current="$form['currency'] ?? null" />
                </x-ui.field>

                @foreach(['price' => __('Price'), 'duration_days' => __('Duration Days'), 'max_users' => __('Max Users'), 'max_projects' => __('Max Projects'), 'max_boqs' => __('Max BOQs'), 'max_ai_credits' => __('AI Credits'), 'max_ocr_pages' => __('OCR Pages'), 'max_translations' => __('Translations'), 'grace_period_days' => __('Grace Days'), 'display_order' => __('Display Order')] as $field => $label)
                    <x-ui.field :label="$label" for="plan-{{ $field }}" :error="'form.'.$field">
                        <input id="plan-{{ $field }}" wire:model="form.{{ $field }}" type="number" @if($field === 'price') step="0.01" @endif min="0" class="boq-field @error('form.'.$field) has-error @enderror" placeholder="{{ in_array($field, ['max_users', 'max_projects', 'max_boqs', 'max_ai_credits', 'max_ocr_pages', 'max_translations'], true) ? __('Blank = unlimited') : '' }}">
                    </x-ui.field>
                @endforeach

                <x-ui.field :label="__('Storage bytes')" for="plan-storage" error="form.max_storage_bytes" :hint="__('e.g. 104857600 = 100 MB')">
                    <input id="plan-storage" wire:model="form.max_storage_bytes" type="number" min="0" class="boq-field @error('form.max_storage_bytes') has-error @enderror">
                </x-ui.field>

                <div class="flex flex-wrap items-center gap-x-6 gap-y-3 rounded-lg border border-slate-200 bg-slate-50 p-3 md:col-span-3">
                    <label class="boq-check"><input type="checkbox" wire:model="form.is_active"> {{ __('Active') }}</label>
                    <label class="boq-check"><input type="checkbox" wire:model="form.has_trial"> {{ __('Trial enabled') }}</label>
                    <label class="boq-check"><input type="checkbox" wire:model="form.auto_renewal"> {{ __('Auto renewal') }}</label>
                    <label class="boq-check" for="plan-trial-days">{{ __('Trial days') }}
                        <input id="plan-trial-days" placeholder="30" wire:model="form.trial_days" type="number" min="0" class="boq-field boq-field-sm w-24">
                    </label>
                    @error('form.trial_days') <p class="boq-field-error w-full">{{ $message }}</p> @enderror
                </div>
            </div>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="cancel">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" icon="fa-floppy-disk" loading="save">{{ __('Save Plan') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    <div x-show="confirmArchive" x-cloak class="boq-modal-backdrop" role="dialog" aria-modal="true" @keydown.escape.window="confirmArchive=false">
        <div class="boq-modal boq-modal-sm" @click.outside="confirmArchive=false">
            <div class="boq-modal-head">
                <h2><i class="fas fa-box-archive" aria-hidden="true"></i> {{ __('Archive plan?') }}</h2>
                <button type="button" @click="confirmArchive=false" class="boq-modal-close" aria-label="{{ __('Close') }}"><i class="fas fa-xmark" aria-hidden="true"></i></button>
            </div>
            <div class="boq-modal-body boq-modal-message">{{ __('Archive') }} <strong x-text="archiveName"></strong>{{ __('? Existing subscriptions will remain and no BOQ data will be deleted.') }}</div>
            <div class="boq-modal-foot">
                <button type="button" @click="confirmArchive=false" class="boq-btn-secondary">{{ __('Cancel') }}</button>
                <button type="button" @click="$wire.archive(archiveId); confirmArchive=false" class="boq-btn-danger">{{ __('Archive') }}</button>
            </div>
        </div>
    </div>
</div>
