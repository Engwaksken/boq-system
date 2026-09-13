<div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-slate-900">Plans & Pricing</h1>
            <p class="mt-1 text-slate-500">Choose the plan that fits your BOQ workflow</p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="stat-card bg-white rounded-2xl shadow-sm border border-slate-200 p-6 border-l-4 border-indigo-500">
            <p class="text-sm font-medium text-slate-500">Total Plans</p>
            <p class="mt-1 text-3xl font-bold text-slate-900">{{ $stats['total_plans'] }}</p>
            <div class="mt-4 flex items-center justify-between">
                <svg class="h-6 w-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2h14a2 2 0 012 2z" /><path stroke-linecap="round" stroke-linejoin="round" d="M7 11l5-5 5 5M7 11v10a2 2 0 002 2h10" /></svg>
            </div>
        </div>
        <div class="stat-card bg-white rounded-2xl shadow-sm border border-slate-200 p-6 border-l-4 border-emerald-500">
            <p class="text-sm font-medium text-slate-500">Active Plans</p>
            <p class="mt-1 text-3xl font-bold text-emerald-600">{{ $stats['active_plans'] }}</p>
            <div class="mt-4 flex items-center justify-between">
                <svg class="h-6 w-6 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
        </div>
        <div class="stat-card bg-white rounded-2xl shadow-sm border border-slate-200 p-6 border-l-4 border-amber-500">
            <p class="text-sm font-medium text-slate-500">Archived Plans</p>
            <p class="mt-1 text-3xl font-bold text-amber-600">{{ $stats['archived_plans'] }}</p>
            <div class="mt-4 flex items-center justify-between">
                <svg class="h-6 w-6 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h14a2 2 0 002-2V8z" /></svg>
            </div>
        </div>
        <div class="stat-card bg-white rounded-2xl shadow-sm border border-slate-200 p-6 border-l-4 border-indigo-500">
            <p class="text-sm font-medium text-slate-500">Total Subscriptions</p>
            <p class="mt-1 text-3xl font-bold text-indigo-600">{{ $stats['total_subscriptions'] }}</p>
            <div class="mt-4 flex items-center justify-between">
                <svg class="h-6 w-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
        </div>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            </div>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search plans..."
                   class="w-full pl-10 rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 w-full sm:w-auto">
            <select wire:model="perPage" class="rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-4 py-2 text-sm">
                @foreach($perPageOptions as $option)
                    <option value="{{ $option }}">{{ $option }} per page</option>
                @endforeach
            </select>

            <div class="flex gap-2">
                @foreach(['name' => 'Name', 'price' => 'Price', 'duration_days' => 'Duration', 'is_active' => 'Status', 'display_order' => 'Order'] as $field => $label)
                    <button wire:click="sortBy('{{ $field }}')" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors {{ $sortBy === $field ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }} whitespace-nowrap">
                        {{ $label }} {{ $sortBy === $field ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        @if(isset($plans) && $plans->isNotEmpty())
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" wire:click="sortBy('name')" style="user-select: none;">
                            Name {{ $sortBy === 'name' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" wire:click="sortBy('price')" style="user-select: none;">
                            Price {{ $sortBy === 'price' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" wire:click="sortBy('duration_days')" style="user-select: none;">
                            Duration {{ $sortBy === 'duration_days' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" wire:click="sortBy('is_active')" style="user-select: none;">
                            Status {{ $sortBy === 'is_active' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer hover:text-slate-700" wire:click="sortBy('display_order')" style="user-select: none;">
                            Order {{ $sortBy === 'display_order' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @foreach($plans as $plan)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ $plan->name }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $plan->currency }} {{ number_format((float) $plan->price, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-slate-500">{{ $plan->duration_days ? $plan->duration_days . ' days' : 'Lifetime' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $plan->is_active && !$plan->is_archived ? 'bg-emerald-100 text-emerald-800' : ($plan->is_archived ? 'bg-slate-100 text-slate-800' : 'bg-red-100 text-red-800') }}">
                                    {{ $plan->is_active && !$plan->is_archived ? 'Active' : ($plan->is_archived ? 'Archived' : 'Inactive') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-500">{{ $plan->display_order }}</td>
                            <td class="px-4 py-3 text-sm">
                                <button class="text-sm font-medium text-indigo-600 hover:text-indigo-800 mr-3">View</button>
                                <button class="text-sm font-medium text-slate-600 hover:text-slate-800">Edit</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if($plans->hasPages())
                <div class="px-4 py-3 border-t border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex-1">
                        <select wire:model="perPage" class="rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-3 py-2 text-sm">
                            @foreach($perPageOptions as $option)
                                <option value="{{ $option }}">{{ $option }} per page</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex-1 sm:ml-auto">
                        {{ $plans->links() }}
                    </div>
                </div>
            @endif
        @else
            <div class="text-center py-12">
                <p class="text-slate-500">No plans available yet.</p>
            </div>
        @endif
    </div>
</div>