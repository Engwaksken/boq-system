<div class="min-h-screen bg-slate-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900">Admin Panel</h1>
                <p class="mt-2 text-slate-600">Super administrator controls and overview.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.settings') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold rounded-lg transition shadow-sm">Site Settings</a>
                <a href="{{ route('admin.payment-gateways') }}" class="inline-flex items-center px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold rounded-lg transition shadow-sm">Payment Gateways</a>
                <a href="{{ route('admin.plans') }}" class="inline-flex items-center px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-semibold rounded-lg">Plans</a>
                <a href="{{ route('admin.users') }}" class="inline-flex items-center px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-semibold rounded-lg">Users</a>
                <a href="{{ route('admin.roles-permissions') }}" class="inline-flex items-center px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-semibold rounded-lg">Roles</a>
                <a href="{{ route('admin.hardware-scanner') }}" class="inline-flex items-center px-4 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-semibold rounded-lg transition">Hardware Scanner</a>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            @foreach($stats as $key => $value)
                <div class="stat-card bg-white rounded-2xl shadow-sm border border-slate-200 p-6" style="border-left: 4px solid {{ [
                    'total_users' => '#3b82f6',
                    'active_subscriptions' => '#10b981',
                    'total_revenue' => '#f59e0b',
                    'plans_count' => '#8b5cf6',
                ][$key] ?? '#6b7280' }};">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-500">{{ str_replace('_', ' ', ucwords($key)) }}</p>
                            <p class="mt-1 text-3xl font-bold text-slate-900">{{ is_numeric($value) ? number_format($value) : $value }}</p>
                        </div>
                        <div class="p-3 rounded-xl bg-{{ [
                            'total_users' => 'blue',
                            'active_subscriptions' => 'emerald',
                            'total_revenue' => 'amber',
                            'plans_count' => 'violet',
                        ][$key] ?? 'gray' }}-100">
                            <svg class="h-6 w-6 text-{{ [
                                'total_users' => 'blue',
                                'active_subscriptions' => 'emerald',
                                'total_revenue' => 'amber',
                                'plans_count' => 'violet',
                            ][$key] ?? 'gray' }}-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                @if($key === 'total_users')
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                @elseif($key === 'active_subscriptions')
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                @elseif($key === 'total_revenue')
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                @else
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                @endif
                            </svg>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="border-b border-slate-200 overflow-x-auto">
                <nav class="flex gap-1 px-4" aria-label="Admin tabs">
                    @foreach($tabs as $key => $label)
                        <button wire:click="$set('activeTab', '{{ $key }}')" class="relative px-4 py-3 text-sm font-medium transition-colors {{ $activeTab === $key ? 'text-indigo-600' : 'text-slate-500 hover:text-slate-700' }} whitespace-nowrap">
                            {{ $label }}
                            @if($activeTab === $key)
                                <span class="absolute bottom-0 left-0 right-0 h-0.5 bg-indigo-600"></span>
                            @endif
                        </button>
                    @endforeach
                </nav>
            </div>

            <div class="p-6">
                @if($activeTab === 'subscriptions')
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
                        <input type="text" wire:model.debounce.300ms="search" placeholder="Search subscriptions..." class="flex-1 max-w-md rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-4 py-2 text-sm">
                        <select wire:model="perPage" class="rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-4 py-2 text-sm">
                            <option value="10">10 per page</option>
                            <option value="20">20 per page</option>
                            <option value="50">50 per page</option>
                            <option value="100">100 per page</option>
                        </select>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">User</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Plan</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Period</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @forelse($subscriptions as $sub)
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3 text-sm">
                                            <div class="font-medium text-slate-900">{{ $sub->user->name }}</div>
                                            <div class="text-sm text-slate-500">{{ $sub->user->email }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-slate-700">{{ $sub->plan->name }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $sub->status === 'active' ? 'bg-emerald-100 text-emerald-800' : ($sub->status === 'expired' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800') }}">
                                                {{ ucfirst($sub->status) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right font-medium text-slate-900">{{ $sub->plan?->currency }} {{ number_format((float)($sub->plan?->price ?? 0), 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-slate-500">{{ $sub->start_date?->format('M j, Y') ?? 'Not started' }} - {{ $sub->end_date?->format('M j, Y') ?? 'Ongoing' }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <button class="text-sm font-medium text-indigo-600 hover:text-indigo-800">View</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-8 text-center text-slate-500">No subscriptions found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{ $subscriptions->links() }}
                @elseif($activeTab === 'plans')
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
                        <input type="text" wire:model.debounce.300ms="search" placeholder="Search plans..." class="flex-1 max-w-md rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-4 py-2 text-sm">
                        <select wire:model="perPage" class="rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-4 py-2 text-sm">
                            <option value="10">10 per page</option>
                            <option value="20">20 per page</option>
                            <option value="50">50 per page</option>
                            <option value="100">100 per page</option>
                        </select>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Price</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Interval</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Features</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @forelse($plans as $plan)
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ $plan->name }}</td>
                                        <td class="px-4 py-3 text-sm text-slate-700">{{ $plan->currency }} {{ number_format($plan->price, 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-slate-500">{{ ucwords(str_replace('_', ' ', $plan->type)) }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $plan->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-800' }}">
                                                {{ $plan->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-slate-500">{{ $plan->features->count() }} features</td>
                                        <td class="px-4 py-3 text-right">
                                            <button class="text-sm font-medium text-indigo-600 hover:text-indigo-800 mr-3">Edit</button>
                                            <button class="text-sm font-medium text-red-600 hover:text-red-800">{{ $plan->is_active ? 'Deactivate' : 'Activate' }}</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-8 text-center text-slate-500">No plans found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{ $plans->links() }}
                @elseif($activeTab === 'users')
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
                        <input type="text" wire:model.debounce.300ms="search" placeholder="Search users..." class="flex-1 max-w-md rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-4 py-2 text-sm">
                        <select wire:model="perPage" class="rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-4 py-2 text-sm">
                            <option value="10">10 per page</option>
                            <option value="20">20 per page</option>
                            <option value="50">50 per page</option>
                            <option value="100">100 per page</option>
                        </select>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Email</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Organisation</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Roles</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @forelse($users as $user)
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ $user->name }}</td>
                                        <td class="px-4 py-3 text-sm text-slate-500">{{ $user->email }}</td>
                                        <td class="px-4 py-3 text-sm text-slate-500">{{ $user->organisation?->name ?? 'Personal' }}</td>
                                        <td class="px-4 py-3 text-sm text-slate-500">{{ $user->roles->pluck('name')->join(', ') ?: 'None' }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $user->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">
                                                {{ $user->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <button class="text-sm font-medium text-indigo-600 hover:text-indigo-800 mr-3">View</button>
                                            <button class="text-sm font-medium text-{{ $user->is_active ? 'red' : 'emerald' }}-600 hover:text-{{ $user->is_active ? 'red' : 'emerald' }}-800">{{ $user->is_active ? 'Deactivate' : 'Activate' }}</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-8 text-center text-slate-500">No users found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{ $users->links() }}
                @elseif($activeTab === 'statistics')
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                            <h3 class="text-lg font-semibold text-slate-900 mb-4">Subscription Revenue</h3>
                            <div class="h-48 flex items-end justify-around">
                                @for($i = 0; $i < 12; $i++)
                                    <div class="flex flex-col items-center flex-1">
                                        <div class="w-full bg-indigo-500 rounded-t" style="height: {{ rand(20, 100) }}%"></div>
                                        <span class="text-xs text-slate-500 mt-2">{{ date('M', mktime(0, 0, 0, $i + 1, 1)) }}</span>
                                    </div>
                                @endfor
                            </div>
                        </div>

                        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                            <h3 class="text-lg font-semibold text-slate-900 mb-4">Active Users</h3>
                            <div class="h-48 flex items-end justify-around">
                                @for($i = 0; $i < 12; $i++)
                                    <div class="flex flex-col items-center flex-1">
                                        <div class="w-full bg-emerald-500 rounded-t" style="height: {{ rand(30, 100) }}%"></div>
                                        <span class="text-xs text-slate-500 mt-2">{{ date('M', mktime(0, 0, 0, $i + 1, 1)) }}</span>
                                    </div>
                                @endfor
                            </div>
                        </div>

                        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                            <h3 class="text-lg font-semibold text-slate-900 mb-4">New Registrations</h3>
                            <div class="h-48 flex items-end justify-around">
                                @for($i = 0; $i < 12; $i++)
                                    <div class="flex flex-col items-center flex-1">
                                        <div class="w-full bg-amber-500 rounded-t" style="height: {{ rand(10, 80) }}%"></div>
                                        <span class="text-xs text-slate-500 mt-2">{{ date('M', mktime(0, 0, 0, $i + 1, 1)) }}</span>
                                    </div>
                                @endfor
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>