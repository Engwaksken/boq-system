<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Subscriptions</h1>
            <p class="mt-1 text-sm text-slate-500">Manage subscription history and choose an available plan.</p>
        </div>
        @if($subscription)
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2">
                <div class="text-xs font-semibold uppercase text-emerald-700">Current subscription</div>
                <div class="font-bold text-emerald-950">{{ $subscription->plan?->name ?? 'Plan' }}</div>
            </div>
        @endif
    </div>

    @if(session('message'))<div class="boq-flash">{{ session('message') }}</div>@endif

    <div class="boq-panel overflow-hidden">
        <div class="boq-admin-tabs">
            <button wire:click="setTab('subscriptions')" class="boq-admin-tab {{ $activeTab === 'subscriptions' ? 'is-active' : '' }}">Subscriptions</button>
            <button wire:click="setTab('plans')" class="boq-admin-tab {{ $activeTab === 'plans' ? 'is-active' : '' }}">Available Plans</button>
        </div>

        @if($activeTab === 'subscriptions')
            <div class="border-b border-slate-200 p-4">
                <div class="grid gap-3 lg:grid-cols-[minmax(240px,1fr)_180px_180px_130px]">
                    <div><label class="boq-field-label">Search</label><input wire:model.live.debounce.300ms="search" class="boq-field" placeholder="Search plan, status or payment status"></div>
                    <div><label class="boq-field-label">Status</label><select wire:model.live="statusFilter" class="boq-field"><option value="all">All statuses</option>@foreach(['pending','trial','active','past_due','grace_period','suspended','expired','cancelled'] as $status)<option value="{{ $status }}">{{ ucwords(str_replace('_', ' ', $status)) }}</option>@endforeach</select></div>
                    <div><label class="boq-field-label">Period</label><select wire:model.live="periodFilter" class="boq-field"><option value="all">All periods</option><option value="current">Current</option><option value="ending_30">Ending in 30 days</option><option value="expired">Expired</option><option value="this_year">Created this year</option></select></div>
                    <div><label class="boq-field-label">Rows</label><select wire:model.live="perPage" class="boq-field">@foreach([10,25,50,100] as $size)<option value="{{ $size }}">{{ $size }}</option>@endforeach</select></div>
                </div>

                @if(count($selectedSubscriptions))
                    <div class="mt-3 flex flex-wrap items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
                        <span class="text-sm font-semibold text-amber-900">{{ count($selectedSubscriptions) }} selected</span>
                        <button wire:click="confirmBulkCancel" class="text-sm font-bold text-red-700">Cancel selected</button>
                        <button wire:click="clearSelection" class="text-sm font-semibold text-slate-600">Clear selection</button>
                    </div>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="boq-table min-w-full divide-y divide-slate-200">
                    <thead><tr><th class="w-10 px-4 py-3 text-left"><input type="checkbox" wire:model.live="selectPage" aria-label="Select page"></th><th class="px-4 py-3 text-left">Plan</th><th class="px-4 py-3 text-left">Status</th><th class="px-4 py-3 text-left">Payment</th><th class="px-4 py-3 text-left">Period</th><th class="px-4 py-3 text-right">Price</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @forelse($subscriptions as $item)
                        <tr>
                            <td class="px-4 py-3"><input type="checkbox" wire:model.live="selectedSubscriptions" value="{{ $item->id }}" aria-label="Select subscription"></td>
                            <td class="px-4 py-3"><div class="font-semibold text-slate-900">{{ $item->plan?->name ?? 'Unknown plan' }}</div><div class="text-xs text-slate-500">{{ $item->plan?->code }}</div></td>
                            <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs font-semibold {{ in_array($item->status,['active','trial','grace_period']) ? 'bg-emerald-100 text-emerald-700' : ($item->status === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700') }}">{{ ucwords(str_replace('_',' ',$item->status)) }}</span></td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ ucwords(str_replace('_',' ',$item->payment_status ?? 'pending')) }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $item->start_date?->format('d M Y') ?? 'Not started' }} <span class="text-slate-400">–</span> {{ $item->end_date?->format('d M Y') ?? 'Ongoing' }}</td>
                            <td class="px-4 py-3 text-right text-sm font-semibold">{{ $item->plan?->currency ?? 'UGX' }} {{ number_format((float)($item->plan?->price ?? 0), 0) }}</td>
                            <td class="px-4 py-3 text-right">@unless(in_array($item->status, ['cancelled','expired']))<button wire:click="confirmCancel({{ $item->id }})" class="text-sm font-semibold text-red-600 hover:text-red-800">Cancel</button>@else<span class="text-xs text-slate-400">No action</span>@endunless</td>
                        </tr>
                    @empty<tr><td colspan="7" class="p-10 text-center text-sm text-slate-500">No subscriptions match your filters.</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 p-4">{{ $subscriptions->links() }}</div>
        @else
            <div class="border-b border-slate-200 p-4">
                <div class="grid gap-3 md:grid-cols-[minmax(260px,1fr)_210px_130px]">
                    <div><label class="boq-field-label">Search plans</label><input wire:model.live.debounce.300ms="planSearch" class="boq-field" placeholder="Search plan name, code or description"></div>
                    <div><label class="boq-field-label">Billing period</label><select wire:model.live="planPeriodFilter" class="boq-field"><option value="all">All periods</option><option value="monthly">Monthly</option><option value="quarterly">3 months</option><option value="six_month">6 months</option><option value="annual">Annual</option><option value="lifetime">Lifetime</option></select></div>
                    <div><label class="boq-field-label">Rows</label><select wire:model.live="planPerPage" class="boq-field">@foreach([10,25,50,100] as $size)<option value="{{ $size }}">{{ $size }}</option>@endforeach</select></div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="boq-table min-w-full divide-y divide-slate-200">
                    <thead><tr><th class="px-4 py-3 text-left">Plan</th><th class="px-4 py-3 text-left">Period</th><th class="px-4 py-3 text-left">Limits</th><th class="px-4 py-3 text-right">Price</th><th class="px-4 py-3 text-right">Action</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @forelse($plans as $plan)
                        <tr>
                            <td class="px-4 py-3"><div class="font-semibold text-slate-900">{{ $plan->name }}</div><div class="mt-1 max-w-xl text-xs text-slate-500">{{ $plan->description ?: 'BOQ subscription plan' }}</div></td>
                            <td class="px-4 py-3 text-sm">{{ $plan->duration_days ? $plan->duration_days.' days' : ucwords(str_replace('_',' ',$plan->type)) }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $plan->max_projects ?? '∞' }} projects · {{ $plan->max_boqs ?? '∞' }} BOQs · {{ $plan->max_ai_credits ?? '∞' }} AI</td>
                            <td class="px-4 py-3 text-right font-bold">{{ $plan->currency }} {{ number_format((float)$plan->price, 0) }}</td>
                            <td class="px-4 py-3 text-right">@if($subscription && $subscription->plan_id === $plan->id)<span class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-500">Current Plan</span>@else<button wire:click="confirmSubscribe({{ $plan->id }})" class="boq-btn-primary">Choose Plan</button>@endif</td>
                        </tr>
                    @empty<tr><td colspan="5" class="p-10 text-center text-sm text-slate-500">No available plans match your search.</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 p-4">{{ $plans->links() }}</div>
        @endif
    </div>

    @if($showActionModal)
        <div class="boq-modal-backdrop" wire:key="subscription-action-modal">
            <div class="boq-modal max-w-md">
                <div class="boq-modal-head"><h2 class="text-lg font-bold text-slate-900">{{ $actionTitle }}</h2><button type="button" wire:click="closeActionModal" class="text-2xl leading-none text-slate-400 hover:text-slate-700">&times;</button></div>
                <div class="boq-modal-body"><p class="text-sm leading-6 text-slate-600">{{ $actionMessage }}</p></div>
                <div class="boq-modal-foot"><button type="button" wire:click="closeActionModal" class="boq-btn-secondary">Back</button><button type="button" wire:click="performAction" class="{{ in_array($actionType,['cancel','bulk_cancel']) ? 'boq-btn-danger' : 'boq-btn-primary' }}">Confirm</button></div>
            </div>
        </div>
    @endif
</div>
