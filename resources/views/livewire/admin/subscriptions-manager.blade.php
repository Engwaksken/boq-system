
<div class="space-y-5" x-data="{ actionModal:false, actionType:'', actionId:null, actionName:'', extendDays:30 }">
    <div><h1 class="text-2xl font-bold">Subscriptions Management</h1><p class="text-sm text-slate-500">Trials, active plans, expiries and payment-backed revenue.</p></div>
    @include('livewire.admin._tabs')
    @if(session('message'))<div class="boq-flash">{{session('message')}}</div>@endif

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
        @foreach(['total'=>'Total','active'=>'Active','expired'=>'Expired','cancelled'=>'Cancelled'] as $key=>$label)<div class="boq-panel border-l-4 border-l-emerald-700 p-4"><div class="text-xs uppercase text-slate-500">{{$label}}</div><div class="text-2xl font-bold">{{number_format($stats[$key])}}</div></div>@endforeach
        <div class="boq-panel border-l-4 border-l-lime-400 p-4"><div class="text-xs uppercase text-slate-500">Revenue</div><div class="text-xl font-bold">UGX {{number_format((float)$stats['revenue'],0)}}</div></div>
    </div>

    <div class="flex flex-wrap gap-3">
        <input wire:model.live.debounce.300ms="search" class="min-w-64 flex-1 rounded-lg border-slate-300 text-sm" placeholder="Search user name or email">
        <select wire:model.live="statusFilter" class="rounded-lg border-slate-300 text-sm"><option value="all">All statuses</option>@foreach(['pending','trial','active','past_due','grace_period','suspended','expired','cancelled'] as $status)<option value="{{$status}}">{{ucwords(str_replace('_',' ',$status))}}</option>@endforeach</select>
        <select wire:model.live="perPage" class="rounded-lg border-slate-300 text-sm">@foreach($perPageOptions as $n)<option value="{{$n}}">{{$n}} per page</option>@endforeach</select>
    </div>

    <div class="boq-panel overflow-x-auto">
        <table class="boq-table min-w-full divide-y divide-slate-200">
            <thead><tr><th class="px-4 py-3 text-left">User</th><th class="px-4 py-3 text-left">Plan</th><th class="px-4 py-3 text-left">Status</th><th class="px-4 py-3 text-left">Payment</th><th class="px-4 py-3 text-right">Plan Price</th><th class="px-4 py-3 text-left">Period</th><th class="px-4 py-3 text-left">Actions</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($subscriptions as $sub)<tr>
                <td class="px-4 py-3"><div class="font-semibold">{{$sub->user?->name ?? 'Deleted user'}}</div><div class="text-xs text-slate-500">{{$sub->user?->email}}</div></td>
                <td class="px-4 py-3">{{$sub->plan?->name ?? '—'}}</td>
                <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs">{{ucwords(str_replace('_',' ',$sub->status))}}</span></td>
                <td class="px-4 py-3 text-sm">{{$sub->payment_status}}</td>
                <td class="px-4 py-3 text-right">{{$sub->plan?->currency}} {{number_format((float)($sub->plan?->price ?? 0),2)}}</td>
                <td class="px-4 py-3 text-sm text-slate-500">{{$sub->start_date?->format('d M Y') ?? 'Not started'}} – {{$sub->end_date?->format('d M Y') ?? 'Ongoing'}}</td>
                <td class="px-4 py-3 whitespace-nowrap">
                    @if(in_array($sub->status,['active','trial','grace_period']))<button @click="actionType='suspend';actionId={{$sub->id}};actionName=@js($sub->user?->name ?? 'subscription');actionModal=true" class="mr-2 text-sm font-semibold text-amber-600">Suspend</button>
                    @elseif($sub->status==='suspended')<button @click="actionType='reactivate';actionId={{$sub->id}};actionName=@js($sub->user?->name ?? 'subscription');actionModal=true" class="mr-2 text-sm font-semibold text-emerald-700">Reactivate</button>@endif
                    <button @click="actionType='extend';actionId={{$sub->id}};actionName=@js($sub->user?->name ?? 'subscription');extendDays=30;actionModal=true" class="mr-2 text-sm font-semibold text-emerald-700">Extend</button>
                    @unless(in_array($sub->status,['cancelled','expired']))<button @click="actionType='cancel';actionId={{$sub->id}};actionName=@js($sub->user?->name ?? 'subscription');actionModal=true" class="text-sm font-semibold text-red-600">Cancel</button>@endunless
                </td>
            </tr>@empty<tr><td colspan="7" class="p-8 text-center text-slate-500">No subscriptions found.</td></tr>@endforelse
            </tbody>
        </table>
        <div class="p-4">{{$subscriptions->links()}}</div>
    </div>

    <div x-show="actionModal" x-cloak class="boq-modal-backdrop">
        <div class="boq-modal max-w-md" @click.stop>
            <div class="boq-modal-head"><h2 class="text-lg font-bold" x-text="actionType==='extend' ? 'Extend subscription' : (actionType.charAt(0).toUpperCase()+actionType.slice(1)+' subscription?')"></h2><button @click="actionModal=false" class="text-2xl text-slate-400">&times;</button></div>
            <div class="boq-modal-body text-sm text-slate-600">
                <p><span x-text="actionType.charAt(0).toUpperCase()+actionType.slice(1)"></span> the subscription for <strong x-text="actionName"></strong>?</p>
                <div x-show="actionType==='extend'" class="mt-4"><label class="text-sm font-medium">Extension</label><select x-model.number="extendDays" class="mt-1 w-full rounded-lg border-slate-300"><option :value="7">7 days</option><option :value="30">30 days</option><option :value="90">90 days</option><option :value="365">365 days</option></select></div>
                <p x-show="actionType==='cancel'" class="mt-3 text-xs text-slate-500">The user's BOQ/project data will be preserved.</p>
            </div>
            <div class="boq-modal-foot">
                <button @click="actionModal=false" class="boq-btn-secondary">Back</button>
                <button @click="
                    actionType==='suspend' ? $wire.suspend(actionId) :
                    actionType==='reactivate' ? $wire.reactivate(actionId) :
                    actionType==='extend' ? $wire.extend(actionId, extendDays) :
                    $wire.cancelSubscription(actionId);
                    actionModal=false
                " :class="actionType==='cancel' ? 'boq-btn-danger' : 'boq-btn-primary'">Confirm</button>
            </div>
        </div>
    </div>
</div>
