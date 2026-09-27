
<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-xs font-bold uppercase tracking-[.16em] text-emerald-700">Super Admin</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">Administration & System Control</h1>
            <p class="mt-1 text-sm text-slate-500">Manage subscriptions, payments, users, access control and system settings.</p>
        </div>
    </div>


    <div class="boq-stats-grid">
        <x-stat-card label="Users" :value="number_format($stats['total_users'])" icon="fa-users" color="green" />
        <x-stat-card label="Active Subscriptions" :value="number_format($stats['active_subscriptions'])" icon="fa-receipt" color="blue" />
        <x-stat-card label="Active Plans" :value="number_format($stats['plans_count'])" icon="fa-layer-group" color="purple" />
        <x-stat-card label="Successful Revenue" :value="'UGX '.number_format((float) $stats['total_revenue'], 0)" icon="fa-sack-dollar" color="amber" />
    </div>

    <div class="boq-panel overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-4">
            <div class="flex flex-wrap gap-1">
                @foreach($tabs as $key => $label)
                    <button wire:click="$set('activeTab','{{ $key }}')"
                        class="rounded-lg px-3 py-2 text-sm font-semibold {{ $activeTab === $key ? 'bg-emerald-800 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
            <input wire:model.live.debounce.300ms="search" class="w-full rounded-lg border-slate-300 text-sm md:w-80" placeholder="Search current tab...">
        </div>

        @if($activeTab === 'subscriptions')
            <div class="overflow-x-auto">
                <table class="boq-table min-w-full divide-y divide-slate-200">
                    <thead><tr>
                        <th class="px-4 py-3 text-left">User</th><th class="px-4 py-3 text-left">Plan</th>
                        <th class="px-4 py-3 text-left">Status</th><th class="px-4 py-3 text-left">Period</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($subscriptions as $sub)
                        <tr>
                            <td class="px-4 py-3"><div class="font-semibold">{{ $sub->user?->name ?? 'Deleted user' }}</div><div class="text-xs text-slate-500">{{ $sub->user?->email }}</div></td>
                            <td class="px-4 py-3 text-sm">{{ $sub->plan?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm">{{ ucwords(str_replace('_',' ',$sub->status)) }}</td>
                            <td class="px-4 py-3 text-sm text-slate-500">{{ $sub->start_date?->format('d M Y') ?? '—' }} – {{ $sub->end_date?->format('d M Y') ?? 'Ongoing' }}</td>
                        </tr>
                        @empty<tr><td colspan="4" class="p-8 text-center text-slate-500">No subscriptions found.</td></tr>@endforelse
                    </tbody>
                </table>
                <div class="p-4">{{ $subscriptions->links() }}</div>
            </div>
        @elseif($activeTab === 'plans')
            <div class="overflow-x-auto">
                <table class="boq-table min-w-full divide-y divide-slate-200">
                    <thead><tr><th class="px-4 py-3 text-left">Plan</th><th class="px-4 py-3 text-left">Price</th><th class="px-4 py-3 text-left">Status</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @forelse($plans as $plan)<tr>
                        <td class="px-4 py-3"><div class="font-semibold">{{ $plan->name }}</div><div class="text-xs text-slate-500">{{ $plan->code }}</div></td>
                        <td class="px-4 py-3">{{ $plan->currency }} {{ number_format((float)$plan->price, 2) }}</td>
                        <td class="px-4 py-3">{{ $plan->is_active ? 'Active' : 'Inactive' }}</td>
                    </tr>@empty<tr><td colspan="3" class="p-8 text-center text-slate-500">No plans found.</td></tr>@endforelse
                    </tbody>
                </table>
                <div class="p-4">{{ $plans->links() }}</div>
            </div>
        @elseif($activeTab === 'users')
            <div class="overflow-x-auto">
                <table class="boq-table min-w-full divide-y divide-slate-200">
                    <thead><tr><th class="px-4 py-3 text-left">User</th><th class="px-4 py-3 text-left">Organisation</th><th class="px-4 py-3 text-left">Roles</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @forelse($users as $user)<tr>
                        <td class="px-4 py-3"><div class="font-semibold">{{ $user->name }}</div><div class="text-xs text-slate-500">{{ $user->email }}</div></td>
                        <td class="px-4 py-3 text-sm">{{ $user->organisation?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm">{{ $user->roles->pluck('name')->join(', ') ?: '—' }}</td>
                    </tr>@empty<tr><td colspan="3" class="p-8 text-center text-slate-500">No users found.</td></tr>@endforelse
                    </tbody>
                </table>
                <div class="p-4">{{ $users->links() }}</div>
            </div>
        @endif
    </div>
</div>
