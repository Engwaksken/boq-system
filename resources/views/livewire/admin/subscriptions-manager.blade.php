<div class="min-h-screen bg-slate-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900">Subscriptions Management</h1>
                <p class="mt-2 text-slate-600">Manage all user subscriptions and plans.</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('admin.index') }}" class="inline-flex items-center px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-sm font-semibold rounded-lg transition">
                    <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                    Back to Admin
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 border-l-4 border-indigo-500">
                <p class="text-sm font-medium text-slate-500">Total Subscriptions</p>
                <p class="mt-1 text-3xl font-bold text-slate-900">{{ $stats['total'] }}</p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 border-l-4 border-emerald-500">
                <p class="text-sm font-medium text-slate-500">Active</p>
                <p class="mt-1 text-3xl font-bold text-emerald-600">{{ $stats['active'] }}</p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 border-l-4 border-red-500">
                <p class="text-sm font-medium text-slate-500">Expired</p>
                <p class="mt-1 text-3xl font-bold text-red-600">{{ $stats['expired'] }}</p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 border-l-4 border-amber-500">
                <p class="text-sm font-medium text-slate-500">Cancelled</p>
                <p class="mt-1 text-3xl font-bold text-amber-600">{{ $stats['cancelled'] }}</p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 border-l-4 border-indigo-500">
                <p class="text-sm font-medium text-slate-500">Monthly Revenue</p>
                <p class="mt-1 text-3xl font-bold text-indigo-600">UGX {{ number_format($stats['revenue'] / 1000, 1) }}K</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex gap-2">
                    <select wire:model="perPage" class="rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-3 py-2 text-sm">
                        @foreach($perPageOptions as $option)
                            <option value="{{ $option }}">{{ $option }} per page</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1 sm:ml-auto">
                    <input type="text" wire:model.debounce.300ms="search" placeholder="Search subscriptions..." class="rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-4 py-2 text-sm w-full sm:w-64">
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" wire:click="sortBy('user.name')" style="user-select: none;">
                                User {{ $sortBy === 'user.name' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" wire:click="sortBy('plan.name')" style="user-select: none;">
                                Plan {{ $sortBy === 'plan.name' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" wire:click="sortBy('status')" style="user-select: none;">
                                Status {{ $sortBy === 'status' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" wire:click="sortBy('amount')" style="user-select: none;">
                                Amount {{ $sortBy === 'amount' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" wire:click="sortBy('start_date')" style="user-select: none;">
                                Start Date {{ $sortBy === 'start_date' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" wire:click="sortBy('end_date')" style="user-select: none;">
                                End Date {{ $sortBy === 'end_date' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($subscriptions as $sub)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-sm">
                                    <div class="font-medium text-slate-900">{{ $sub->user?->name ?? 'Deleted user' }}</div>
                                    <div class="text-sm text-slate-500 truncate max-w-xs">{{ $sub->user?->email ?? '—' }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $sub->plan?->name ?? 'Plan unavailable' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ match($sub->status) {
                                        'active' => 'bg-emerald-100 text-emerald-800',
                                        'expired' => 'bg-red-100 text-red-800',
                                        'cancelled' => 'bg-slate-100 text-slate-800',
                                        'pending' => 'bg-amber-100 text-amber-800',
                                        default => 'bg-slate-100 text-slate-800',
                                    } }}">
                                        {{ ucfirst($sub->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-right font-medium text-slate-900">{{ $sub->plan?->currency ?? 'UGX' }} {{ number_format((float) ($sub->plan?->price ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-sm text-slate-500">{{ $sub->start_date?->format('M j, Y') ?? 'Not started' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-500">{{ $sub->end_date?->format('M j, Y') ?? 'Ongoing' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-400">
                                    —
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-slate-500">
                                    <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
                                    <p class="mt-4 text-lg font-medium text-slate-900">No subscriptions found</p>
                                    <p class="mt-2 text-slate-500">No subscriptions match your search criteria.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-200">
                {{ $subscriptions->links() }}
            </div>
        </div>
    </div>
</div>