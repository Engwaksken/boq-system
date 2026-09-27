
<div class="space-y-5" x-data="{ confirmArchive: false, archiveId: null, archiveName: '' }">
    <div class="boq-page-header">
        <div>
            <h1 class="boq-page-title"><i class="fas fa-layer-group"></i> Subscription Plans</h1>
            <p class="boq-page-subtitle">Create and manage pricing, limits and trial eligibility.</p>
        </div>
        <button type="button" wire:click="create" class="boq-btn-primary"><i class="fas fa-plus"></i> Add Plan</button>
    </div>

    @if(session('message'))<div class="boq-flash"><i class="fas fa-circle-check"></i> {{ session('message') }}</div>@endif

    <div class="boq-stats-grid">
        <x-stat-card label="Total Plans" :value="\App\Support\Format::number($stats['total'], 0)" icon="fa-layer-group" color="green" />
        <x-stat-card label="Active Plans" :value="\App\Support\Format::number($stats['active'], 0)" icon="fa-circle-check" color="blue" />
        <x-stat-card label="Trial Enabled" :value="\App\Support\Format::number($stats['trial'], 0)" icon="fa-hourglass-half" color="amber" />
        <x-stat-card label="Active Subscribers" :value="\App\Support\Format::number($stats['subscribers'], 0)" icon="fa-users" color="purple" />
    </div>

    <div class="boq-panel overflow-hidden">
        <div class="boq-admin-filter-row">
            <div class="boq-input-icon-wrap">
                <i class="fas fa-magnifying-glass boq-input-icon"></i>
                <input wire:model.live.debounce.300ms="search" placeholder="Search plans..." class="boq-field boq-field-with-icon">
            </div>
        </div>

        <x-bulk-bar :count="count($selected)">
            <button type="button" wire:click="bulkSetActive(true)" class="boq-btn-secondary"><i class="fas fa-circle-check"></i> Activate</button>
            <button type="button" wire:click="bulkSetActive(false)" class="boq-btn-secondary"><i class="fas fa-ban"></i> Deactivate</button>
            <button type="button" wire:click="bulkArchive" wire:confirm="Archive the selected plans? Existing subscriptions are kept." class="boq-btn-danger"><i class="fas fa-box-archive"></i> Archive</button>
            <button type="button" wire:click="bulkDelete" wire:confirm="Delete the selected plans? Plans with subscriptions are skipped." class="boq-btn-danger"><i class="fas fa-trash"></i> Delete</button>
        </x-bulk-bar>

        <div class="boq-table-wrapper">
            <table class="boq-table">
                <thead>
                    <tr>
                        <th class="boq-check-col"><x-select-all :ids="$plans->pluck('id')" :selected="$selected" /></th>
                        <th>Plan</th>
                        <th>Price</th>
                        <th>Duration</th>
                        <th>Limits</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($plans as $plan)
                    <tr wire:key="plan-{{ $plan->id }}">
                        <td class="boq-check-col"><x-select-row :id="$plan->id" /></td>
                        <td><div class="boq-table-title">{{ $plan->name }}</div><div class="boq-table-subtitle">{{ $plan->code }}</div></td>
                        <td>{{ $plan->currency }} {{ \App\Support\Format::number((float) $plan->price, 2) }}</td>
                        <td>{{ $plan->duration_days ?: '—' }} days</td>
                        <td class="text-sm">{{ $plan->max_projects ?? '∞' }} projects · {{ $plan->max_boqs ?? '∞' }} BOQs · {{ $plan->max_ai_credits ?? '∞' }} AI</td>
                        <td>
                            @if($plan->is_archived)
                                <span class="boq-badge boq-badge-danger">Archived</span>
                            @else
                                <span class="boq-badge {{ $plan->is_active ? 'boq-badge-success' : 'boq-badge-warning' }}">{{ $plan->is_active ? 'Active' : 'Inactive' }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="boq-table-actions justify-end">
                                <button type="button" wire:click="edit({{ $plan->id }})" class="boq-icon-btn" title="Edit" aria-label="Edit"><i class="fas fa-pen"></i></button>
                                <button type="button" wire:click="toggleActive({{ $plan->id }})" class="boq-icon-btn" title="{{ $plan->is_active ? 'Deactivate' : 'Activate' }}" aria-label="{{ $plan->is_active ? 'Deactivate' : 'Activate' }}"><i class="fas {{ $plan->is_active ? 'fa-ban' : 'fa-circle-check' }}"></i></button>
                                @unless($plan->is_archived)
                                    <button type="button" @click="archiveId={{ $plan->id }}; archiveName=@js($plan->name); confirmArchive=true" class="boq-icon-btn boq-icon-danger" title="Archive" aria-label="Archive"><i class="fas fa-box-archive"></i></button>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="boq-table-empty">No plans found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($plans->hasPages())<div class="boq-pagination">{{ $plans->links() }}</div>@endif
    </div>

    @if($showForm)
    <div class="boq-modal-backdrop" wire:key="plan-form-modal" x-data @keydown.escape.window="$wire.cancel()">
        <div class="boq-modal boq-modal-lg" @click.stop>
            <div class="boq-modal-head">
                <div><h2 class="text-lg font-bold">{{ $editingId ? 'Edit Plan' : 'Create Plan' }}</h2><p class="text-xs text-slate-500">Changes are saved without leaving this page.</p></div>
                <button type="button" wire:click="cancel" class="text-2xl leading-none text-slate-400 hover:text-slate-700">&times;</button>
            </div>
            <form wire:submit="save">
                <div class="boq-modal-body grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div><label class="text-sm font-medium">Name</label><input placeholder="Enter name" wire:model="form.name" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div><label class="text-sm font-medium">Code</label><input wire:model="form.code" class="mt-1 w-full rounded-lg border-slate-300" placeholder="auto-generated"></div>
                    <div><label class="text-sm font-medium">Type</label><select wire:model="form.type" class="mt-1 w-full rounded-lg border-slate-300"><option value="monthly">Monthly</option><option value="three_month">3 Months</option><option value="six_month">6 Months</option><option value="annual">Annual</option><option value="one_time">One Time</option><option value="lifetime">Lifetime</option></select></div>
                    <div class="md:col-span-3"><label class="text-sm font-medium">Description</label><textarea placeholder="Add description..." wire:model="form.description" rows="2" class="mt-1 w-full rounded-lg border-slate-300"></textarea></div>
                    <div><label class="text-sm font-medium">Currency</label><x-currency-select wire:model="form.currency" :current="$form['currency'] ?? null" class="mt-1" /></div>
                    @foreach(['price'=>'Price','duration_days'=>'Duration Days','max_users'=>'Max Users','max_projects'=>'Max Projects','max_boqs'=>'Max BOQs','max_ai_credits'=>'AI Credits','max_ocr_pages'=>'OCR Pages','max_translations'=>'Translations','grace_period_days'=>'Grace Days','display_order'=>'Display Order'] as $field=>$label)
                    <div><label class="text-sm font-medium">{{ $label }}</label><input placeholder="{{ \Illuminate\Support\Str::headline($field) }}" wire:model="form.{{ $field }}" class="mt-1 w-full rounded-lg border-slate-300" type="{{ $field==='currency' ? 'text' : 'number' }}"></div>
                    @endforeach
                    <div><label class="text-sm font-medium">Storage bytes</label><input placeholder="e.g. 104857600 (100 MB)" wire:model="form.max_storage_bytes" type="number" class="mt-1 w-full rounded-lg border-slate-300"></div>
                    <div class="flex flex-wrap items-center gap-5 md:col-span-3">
                        <label><input type="checkbox" wire:model="form.is_active"> Active</label>
                        <label><input type="checkbox" wire:model="form.has_trial"> Trial enabled</label>
                        <label><input type="checkbox" wire:model="form.auto_renewal"> Auto renewal</label>
                        <label class="text-sm">Trial days <input placeholder="e.g. 30" wire:model="form.trial_days" type="number" class="ml-2 w-24 rounded-lg border-slate-300"></label>
                    </div>
                    @if($errors->any())<div class="md:col-span-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
                </div>
                <div class="boq-modal-foot"><button type="button" wire:click="cancel" class="boq-btn-secondary">Cancel</button><button class="boq-btn-primary">Save Plan</button></div>
            </form>
        </div>
    </div>
    @endif

    <div x-show="confirmArchive" x-cloak class="boq-modal-backdrop" @keydown.escape.window="confirmArchive=false">
        <div class="boq-modal boq-modal-sm" @click.stop>
            <div class="boq-modal-head"><h2 class="text-lg font-bold">Archive plan?</h2><button @click="confirmArchive=false" class="text-2xl text-slate-400">&times;</button></div>
            <div class="boq-modal-body text-sm text-slate-600">Archive <strong x-text="archiveName"></strong>? Existing subscriptions will remain and no BOQ data will be deleted.</div>
            <div class="boq-modal-foot"><button @click="confirmArchive=false" class="boq-btn-secondary">Cancel</button><button @click="$wire.archive(archiveId); confirmArchive=false" class="boq-btn-danger">Archive</button></div>
        </div>
    </div>
</div>
