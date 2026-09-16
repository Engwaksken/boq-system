<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'BOQ System' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="font-sans antialiased">
    @php
        $user = Auth::user();
        $isSuperAdmin = $user?->isSuperAdmin() ?? false;

        $workspaceNavigation = [
            ['label' => 'Dashboard', 'url' => url('/dashboard'), 'active' => request()->is('dashboard'), 'icon' => 'dashboard'],
            ['label' => 'Projects', 'url' => url('/projects'), 'active' => request()->is('projects*'), 'icon' => 'projects'],
            ['label' => 'BOQs', 'url' => url('/boqs'), 'active' => request()->is('boqs*'), 'icon' => 'boqs'],
            ['label' => 'Hardware Prices', 'url' => url('/hardware-prices'), 'active' => request()->is('hardware-prices*'), 'icon' => 'prices'],
            ['label' => 'Plans', 'url' => url('/plans'), 'active' => request()->is('plans*'), 'icon' => 'plans'],
            ['label' => 'Subscriptions', 'url' => url('/subscriptions'), 'active' => request()->is('subscriptions*'), 'icon' => 'subscription'],
        ];

        $adminNavigation = [
            ['label' => 'Admin Overview', 'url' => url('/admin'), 'active' => request()->is('admin'), 'icon' => 'admin'],
            ['label' => 'Plans Management', 'url' => url('/admin/plans'), 'active' => request()->is('admin/plans*'), 'icon' => 'plans'],
            ['label' => 'Subscriptions', 'url' => url('/admin/subscriptions'), 'active' => request()->is('admin/subscriptions*'), 'icon' => 'subscription'],
            ['label' => 'Payment Gateways', 'url' => url('/admin/payment-gateways'), 'active' => request()->is('admin/payment-gateways*'), 'icon' => 'payment'],
            ['label' => 'Users', 'url' => url('/admin/users'), 'active' => request()->is('admin/users*'), 'icon' => 'users'],
            ['label' => 'Roles & Permissions', 'url' => url('/admin/roles-permissions'), 'active' => request()->is('admin/roles-permissions*'), 'icon' => 'roles'],
            ['label' => 'Hardware Scanner', 'url' => url('/admin/hardware-scanner'), 'active' => request()->is('admin/hardware-scanner*'), 'icon' => 'scanner'],
            ['label' => 'System Settings', 'url' => url('/admin/settings'), 'active' => request()->is('admin/settings*'), 'icon' => 'settings'],
        ];
    @endphp

    @php
        $navIcon = function (string $icon) {
            return match ($icon) {
                'dashboard' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 13h8V3H3v10Zm10 8h8V11h-8v10ZM3 21h8v-6H3v6Zm10-12h8V3h-8v6Z"/>',
                'projects' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 7h6l2 2h10v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 7V5a2 2 0 0 1 2-2h4l2 2h5"/>',
                'boqs' => '<path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M14 3v6h6M9 13h6M9 17h6"/>',
                'prices' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18M17 7.5c0-1.38-2.24-2.5-5-2.5S7 6.12 7 7.5 9.24 10 12 10s5 1.12 5 2.5S14.76 15 12 15s-5-1.12-5-2.5"/>',
                'plans' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v14H4zM8 9h8M8 13h5"/>',
                'subscription' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h3"/>',
                'admin' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3 4 7v5c0 5 3.4 8.6 8 10 4.6-1.4 8-5 8-10V7l-8-4Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9.5 12.5 11 14l3.5-4"/>',
                'payment' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 9h18M15 15h2"/>',
                'users' => '<path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
                'roles' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3 5 6v5c0 4.4 2.9 7.7 7 9 4.1-1.3 7-4.6 7-9V6l-7-3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 11h6M12 8v6"/>',
                'scanner' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 7V5a1 1 0 0 1 1-1h2M17 4h2a1 1 0 0 1 1 1v2M20 17v2a1 1 0 0 1-1 1h-2M7 20H5a1 1 0 0 1-1-1v-2M8 8h8v8H8z"/>',
                'settings' => '<circle cx="12" cy="12" r="3"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21h-4v-.1A1.7 1.7 0 0 0 8.6 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H3v-4h.1A1.7 1.7 0 0 0 4.6 8.6a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V3h4v.1A1.7 1.7 0 0 0 15.4 4.6a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 9c.2.36.52.65.9.82.33.15.7.2 1.06.18H21v4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
                default => '<circle cx="12" cy="12" r="2"/>',
            };
        };
    @endphp

    <div x-data="{ sidebarOpen: false }" class="min-h-screen bg-slate-100">
        <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false" class="fixed inset-0 z-40 bg-slate-950/50 backdrop-blur-sm lg:hidden"></div>

        <aside x-cloak :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" class="boq-sidebar fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col text-white transition-transform duration-200 lg:translate-x-0">
            <div class="flex h-20 items-center justify-between border-b border-white/10 px-5">
                <a href="{{ url('/dashboard') }}" class="flex items-center gap-3 min-w-0">
                    <span class="boq-brand-icon flex h-11 w-11 shrink-0 items-center justify-center rounded-xl">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h10M4 18h10" /></svg>
                    </span>
                    <span class="min-w-0"><span class="block truncate text-base font-bold tracking-wide">BOQ System</span><span class="block truncate text-xs text-slate-400">AI cost intelligence</span></span>
                </a>
                <button @click="sidebarOpen = false" class="rounded-lg p-2 text-slate-400 hover:bg-white/10 hover:text-white lg:hidden" aria-label="Close navigation">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <nav class="boq-sidebar-scroll flex-1 overflow-y-auto px-3 py-5">
                <p class="mb-2 px-3 text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500">Workspace</p>
                <div class="space-y-1">
                    @foreach($workspaceNavigation as $item)
                        <a href="{{ $item['url'] }}" class="boq-nav-link {{ $item['active'] ? 'is-active' : '' }}">
                            <span class="boq-nav-icon">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9">{!! $navIcon($item['icon']) !!}</svg>
                            </span>
                            <span class="truncate">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>

                @if($isSuperAdmin)
                    <div class="my-5 border-t border-white/10"></div>
                    <div class="mb-2 flex items-center justify-between px-3">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500">Administration</p>
                        <span class="rounded-full bg-lime-300/15 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-lime-300">Super Admin</span>
                    </div>
                    <div class="space-y-1">
                        @foreach($adminNavigation as $item)
                            <a href="{{ $item['url'] }}" class="boq-nav-link {{ $item['active'] ? 'is-active admin-active' : '' }}">
                                <span class="boq-nav-icon">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9">{!! $navIcon($item['icon']) !!}</svg>
                                </span>
                                <span class="truncate">{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </nav>

            <div class="border-t border-white/10 p-3">
                @if($isSuperAdmin)
                    <a href="{{ url('/admin') }}" class="mb-2 flex items-center justify-between rounded-xl border border-lime-300/15 bg-lime-300/5 px-3 py-2 text-xs text-lime-200 transition hover:bg-lime-300/10">
                        <span>Administration console</span>
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" /></svg>
                    </a>
                @endif
                <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-xl p-3 transition {{ request()->is('profile') ? 'bg-white/10' : 'hover:bg-white/10' }}">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-lime-300 text-sm font-bold text-slate-950">{{ strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}</span>
                    <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold">{{ Auth::user()->name }}</span><span class="block truncate text-xs text-slate-400">{{ $isSuperAdmin ? 'Super Administrator' : 'Account' }}</span></span>
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" /></svg>
                </a>
                <form method="POST" action="{{ route('logout') }}" class="mt-1">
                    @csrf
                    <button type="submit" class="w-full rounded-xl px-3 py-2 text-left text-sm font-medium text-slate-400 transition hover:bg-white/10 hover:text-white">Log out</button>
                </form>
            </div>
        </aside>

        <div class="min-h-screen lg:pl-72">
            <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6 lg:px-8">
                <div class="flex items-center min-w-0">
                    <button @click="sidebarOpen = true" class="mr-3 rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="Open navigation">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    </button>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-slate-900">{{ $title ?? 'BOQ System' }}</p>
                        <p class="hidden truncate text-xs text-slate-500 sm:block">Civil works BOQ, pricing and subscription management</p>
                    </div>
                </div>
                @if($isSuperAdmin)
                    <a href="{{ url('/admin') }}" class="hidden items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-lime-300 hover:text-slate-950 sm:inline-flex">
                        <span class="h-2 w-2 rounded-full bg-lime-400"></span>
                        Super Admin
                    </a>
                @endif
            </header>

            <main class="px-4 py-6 sm:px-6 sm:py-8 xl:px-10">
                <div class="mx-auto max-w-7xl">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>
