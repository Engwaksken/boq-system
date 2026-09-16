<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
        <p class="mt-1 text-sm text-gray-500">Overview of your BOQ workspace</p>
    </div>

    {{-- Stat Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Projects</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900">{{ $projectsCount ?? 0 }}</p>
                </div>
                <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-indigo-100 text-indigo-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                </span>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">BOQs</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900">{{ $boqsCount ?? 0 }}</p>
                </div>
                <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-blue-100 text-blue-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </span>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Hardware Prices Tracked</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900">{{ $hardwarePricesCount ?? 0 }}</p>
                </div>
                <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-green-100 text-green-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Subscription</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900">{{ isset($subscription) && $subscription ? $subscription->plan?->name : 'Free' }}</p>
                </div>
                <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-purple-100 text-purple-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h2m4 0h2m-9 4h10a2 2 0 002-2V8a2 2 0 00-2-2H7a2 2 0 00-2 2v9a2 2 0 002 2z" />
                    </svg>
                </span>
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="flex flex-wrap gap-3 mb-8">
        <a href="{{ url('/projects/create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold rounded-lg transition">
            <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
            New Project
        </a>
        <a href="{{ url('/boqs/create') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-lg transition">
            <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
            New BOQ
        </a>
        <a href="{{ url('/hardware-prices/compare') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-lg transition">
            <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
            Compare Prices
        </a>
        <a href="{{ url('/plans') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-lg transition">
            <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h2m4 0h2m-9 4h10a2 2 0 002-2V8a2 2 0 00-2-2H7a2 2 0 00-2 2v9a2 2 0 002 2z" /></svg>
            View Plans
        </a>
    </div>

    @if(Auth::user()?->isSuperAdmin())
        {{-- Super Admin Management --}}
        <section class="boq-admin-hero mb-8 rounded-2xl p-5 sm:p-6">
            <div class="relative z-10 mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="mb-2 inline-flex items-center gap-2 rounded-full bg-[#05645b]/10 px-3 py-1 text-xs font-bold uppercase tracking-wide text-[#05645b]">
                        <span class="h-2 w-2 rounded-full bg-[#d7df21]"></span>
                        Super Admin
                    </div>
                    <h2 class="text-xl font-bold text-slate-900">Administration & System Control</h2>
                    <p class="mt-1 text-sm text-slate-600">Manage subscriptions, payments, users, access control, pricing automation and system settings.</p>
                </div>
                <a href="{{ url('/admin') }}" class="boq-primary-button inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold shadow-sm transition">
                    Open Admin Console
                    <svg class="ml-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" /></svg>
                </a>
            </div>

            <div class="relative z-10 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @php
                    $adminCards = [
                        ['title' => 'Plans', 'description' => 'Create and manage subscription plans.', 'url' => url('/admin/plans'), 'icon' => 'M4 5h16v14H4zM8 9h8M8 13h5'],
                        ['title' => 'Subscriptions', 'description' => 'Trials, active plans, expiry and extensions.', 'url' => url('/admin/subscriptions'), 'icon' => 'M3 10h18M7 15h2m4 0h2M6 5h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z'],
                        ['title' => 'Payment Gateways', 'description' => 'Direct providers, ioTec and aggregators.', 'url' => url('/admin/payment-gateways'), 'icon' => 'M3 9h18M7 15h3m5 0h2M5 5h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z'],
                        ['title' => 'Users', 'description' => 'Accounts, access status and subscription links.', 'url' => url('/admin/users'), 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87'],
                        ['title' => 'Roles & Permissions', 'description' => 'Control access across all modules.', 'url' => url('/admin/roles-permissions'), 'icon' => 'M12 3 5 6v5c0 4.4 2.9 7.7 7 9 4.1-1.3 7-4.6 7-9V6l-7-3ZM9 11h6M12 8v6'],
                        ['title' => 'Hardware Scanner', 'description' => 'Run and monitor AI hardware price fetching.', 'url' => url('/admin/hardware-scanner'), 'icon' => 'M4 7V5a1 1 0 0 1 1-1h2M17 4h2a1 1 0 0 1 1 1v2M20 17v2a1 1 0 0 1-1 1h-2M7 20H5a1 1 0 0 1-1-1v-2M8 8h8v8H8z'],
                        ['title' => 'System Settings', 'description' => 'Branding, trial duration, AI and defaults.', 'url' => url('/admin/settings'), 'icon' => 'M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0 0 15 19.4'],
                        ['title' => 'Admin Overview', 'description' => 'Revenue, users, subscriptions and plans.', 'url' => url('/admin'), 'icon' => 'M3 13h8V3H3v10Zm10 8h8V11h-8v10ZM3 21h8v-6H3v6Zm10-12h8V3h-8v6Z'],
                    ];
                @endphp

                @foreach($adminCards as $card)
                    <a href="{{ $card['url'] }}" class="boq-admin-card group rounded-xl p-4">
                        <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-[#05645b]/10 text-[#05645b] transition group-hover:bg-[#05645b] group-hover:text-white">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $card['icon'] }}" /></svg>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-sm font-bold text-slate-900">{{ $card['title'] }}</h3>
                            <svg class="h-4 w-4 text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-[#05645b]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" /></svg>
                        </div>
                        <p class="mt-1.5 text-xs leading-5 text-slate-500">{{ $card['description'] }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Recent Projects --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Recent Projects</h2>
            <a href="{{ url('/projects') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">View all</a>
        </div>

        @if(isset($recentProjects) && $recentProjects->isNotEmpty())
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Code</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Contract Value</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @foreach($recentProjects as $project)
                        <tr>
                            <td class="px-6 py-3 text-sm">
                                <a href="{{ url('/projects/' . $project->id) }}" class="font-medium text-indigo-600 hover:text-indigo-500">{{ $project->name }}</a>
                            </td>
                            <td class="px-6 py-3 text-sm text-gray-700">{{ $project->code }}</td>
                            <td class="px-6 py-3 text-sm">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $project->status === 'active' ? 'bg-green-100 text-green-800' : ($project->status === 'completed' ? 'bg-blue-100 text-blue-800' : ($project->status === 'archived' ? 'bg-gray-100 text-gray-800' : 'bg-amber-100 text-amber-800')) }}">
                                    {{ $project->status }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-sm text-gray-700">{{ number_format((float) $project->contract_value, 2) }} {{ $project->currency }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="text-center py-12">
                <p class="text-gray-500">No projects yet.</p>
                <a href="{{ url('/projects/create') }}" class="inline-flex items-center px-4 py-2 mt-4 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold rounded-lg transition">
                    Create your first project
                </a>
            </div>
        @endif
    </div>
</div>
