@php
    // Interface preferences, applied server side so the first paint already
    // has the right theme, accent, density and font size.
    $uiPrefs = Auth::user()?->interfacePreferences() ?? [
        'theme' => 'light', 'accent' => 'green', 'density' => 'comfortable', 'sidebar' => 'expanded', 'font' => 'default',
    ];
@endphp
<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-theme="{{ $uiPrefs['theme'] }}"
    data-accent="{{ $uiPrefs['accent'] }}"
    data-density="{{ $uiPrefs['density'] }}"
    data-font="{{ $uiPrefs['font'] }}"
    data-sidebar="{{ $uiPrefs['sidebar'] }}"
>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#05645b">
    <meta name="color-scheme" content="{{ ['dark' => 'dark', 'system' => 'light dark'][$uiPrefs['theme']] ?? 'light' }}">
    <meta name="description" content="{{ $metaDescription ?? 'BOQ System for project cost planning, BOQ management and market pricing.' }}">
    <meta name="robots" content="noindex,nofollow">

    @php
        $siteName = \App\Models\SiteSetting::get('system_name', 'BOQ System') ?: 'BOQ System';
        $siteLogo = \App\Models\SiteSetting::get('logo', '');
        $siteFavicon = \App\Models\SiteSetting::get('favicon', '');

        $authUser = Auth::user();

        $item = fn (string $label, string $routeName, string $icon, bool $show = true, ?string $pattern = null, array $params = []) => [
            'label' => $label,
            'url' => route($routeName, $params),
            'icon' => $icon,
            'show' => $show,
            'active' => $pattern ? request()->routeIs($pattern) : request()->routeIs($routeName),
        ];

        /*
         * Sidebar navigation, grouped. Visibility rules are unchanged from the
         * previous layout: permission checks for workspace items, super admin
         * only for administration.
         */
        $navGroups = [
            [
                'label' => 'Workspace',
                'items' => [
                    $item('Dashboard', 'dashboard', 'fa-chart-line'),
                    $item('Projects', 'projects.index', 'fa-folder-open', $authUser->hasPermission('projects.view'), 'projects.*'),
                    $item('BOQs', 'boqs.index', 'fa-file-invoice-dollar', $authUser->hasPermission('boq.view'), 'boqs.*'),
                    $item('Get Prices', 'hardware-prices.index', 'fa-tags', $authUser->hasPermission('hardware-prices.view'), 'hardware-prices.*'),
                    $item('Top Suppliers', 'supplier-ratings.index', 'fa-ranking-star', $authUser->hasPermission('hardware-prices.view'), 'supplier-ratings.*'),
                ],
            ],
            [
                // Not plain "Billing": that key collides with lang/*/billing.php.
                'label' => 'Plans & Billing',
                'items' => [
                    $item('Plans', 'plans.index', 'fa-layer-group', true, 'plans.*'),
                    $item('Subscriptions', 'subscriptions.index', 'fa-credit-card', $authUser->hasPermission('subscriptions.view'), 'subscriptions.*'),
                    $item('Top-ups', 'topups.index', 'fa-gift', true, 'topups.*'),
                ],
            ],
            [
                'label' => 'Account',
                'items' => [
                    $item('Preferences', 'preferences', 'fa-palette'),
                ],
            ],
        ];

        if ($authUser->isSuperAdmin()) {
            $navGroups[] = [
                'label' => 'Administration',
                'admin' => true,
                'items' => [
                    $item('Overview', 'admin.index', 'fa-gauge'),
                    $item('Users', 'admin.users', 'fa-users'),
                    $item('Roles & Permissions', 'admin.roles-permissions', 'fa-user-shield'),
                    $item('Plans', 'admin.plans', 'fa-layer-group'),
                    $item('Top-ups', 'admin.topups', 'fa-gift'),
                    $item('Subscriptions', 'admin.subscriptions', 'fa-receipt'),
                    $item('Payment Gateways', 'admin.payment-gateways', 'fa-building-columns'),
                    $item('Rate Library', 'admin.rates', 'fa-book'),
                    $item('Suppliers', 'admin.suppliers', 'fa-truck'),
                    // Project types and BOQ work sections (route lives on main).
                    ...(\Illuminate\Support\Facades\Route::has('admin.categories')
                        ? [$item('Categories', 'admin.categories', 'fa-list-check', true, 'admin.categories')]
                        : []),
                    $item('Quotations', 'admin.quotations', 'fa-file-invoice'),
                    $item('Hardware Scanner', 'admin.hardware-scanner', 'fa-magnifying-glass-dollar'),
                    $item('AI API Settings', 'admin.ai-providers', 'fa-robot'),
                    $item('Versions', 'admin.versions', 'fa-box-open'),
                    $item('Settings', 'admin.settings', 'fa-gear'),
                ],
            ];
        }

        if ($authUser->hasAnyRole(['administrator', 'admin', 'manager', 'super-admin'])) {
            $navGroups[] = [
                'label' => 'System',
                'items' => [
                    // Super admins find Categories under Administration.
                    $item('Categories', 'admin.categories', 'fa-list-check', ! $authUser->isSuperAdmin() && $authUser->hasAnyRole(['administrator', 'admin']), 'admin.categories'),
                    $item('AI Activity', 'system.mcp-activity', 'fa-wave-square'),
                ],
            ];
        }

        foreach ($navGroups as $index => $group) {
            $navGroups[$index]['items'] = array_values(array_filter($group['items'], fn (array $link) => $link['show']));
        }

        $navGroups = array_values(array_filter($navGroups, fn (array $group) => $group['items'] !== []));

        // Breadcrumb: group > section > sub-page, derived from the current route.
        $currentGroup = null;
        $currentItem = null;

        foreach ($navGroups as $group) {
            foreach ($group['items'] as $link) {
                if ($link['active']) {
                    $currentGroup = $group;
                    $currentItem = $link;
                    break 2;
                }
            }
        }

        $routeName = (string) request()->route()?->getName();

        $subPage = match (true) {
            str_ends_with($routeName, '.create') => 'New',
            str_ends_with($routeName, '.edit') && $routeName !== 'profile.edit' => 'Edit',
            str_ends_with($routeName, '.show') => 'Details',
            $routeName === 'hardware-prices.compare' => 'Compare',
            $routeName === 'hardware-prices.recommendations' => 'Recommendations',
            default => null,
        };

        $pageLabel = match (true) {
            $routeName === 'profile.edit' => 'Profile',
            $routeName === 'checkout' => 'Checkout',
            default => $currentItem['label'] ?? null,
        };

        $documentTitle = isset($title) && $title !== ''
            ? $title
            : ($pageLabel ? __($pageLabel).($subPage ? ' · '.__($subPage) : '') : null);

        $initials = collect(preg_split('/\s+/', trim((string) $authUser->name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('') ?: '?';
    @endphp

    <title>{{ $documentTitle ? $documentTitle.' | '.$siteName : $siteName }}</title>

    <link rel="icon" href="{{ $siteFavicon ? asset('storage/'.$siteFavicon) : (\App\Support\PwaIcons::source() ? \App\Support\PwaIcons::url('icon-192') : asset('favicon.ico')) }}">
    @include('pwa.head')

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')

    @livewireStyles
</head>

<body class="boq-app-shell font-sans antialiased">

<a href="#main-content" class="boq-skip-link">{{ __('Skip to content') }}</a>

<div
    x-data="{
        sidebarOpen: false,
        {{-- Default from Preferences; a manual toggle is remembered in this browser. --}}
        collapsed: @js($uiPrefs['sidebar'] === 'collapsed'),
        init() {
            try {
                const remembered = localStorage.getItem('boq.sidebar.collapsed');
                if (remembered !== null) { this.collapsed = remembered === '1'; }
            } catch (e) {}
        },
        toggleCollapsed() {
            this.collapsed = ! this.collapsed;
            try { localStorage.setItem('boq.sidebar.collapsed', this.collapsed ? '1' : '0'); } catch (e) {}
        },
    }"
    x-on:keydown.escape.window="sidebarOpen = false"
    x-on:boq-sidebar-preference.window="collapsed = $event.detail === 'collapsed'"
    :class="{ 'boq-shell-collapsed': collapsed }"
    class="min-h-screen {{ $uiPrefs['sidebar'] === 'collapsed' ? 'boq-shell-collapsed' : '' }}"
>
    {{-- Mobile overlay --}}
    <div
        x-show="sidebarOpen"
        x-cloak
        x-transition.opacity
        @click="sidebarOpen = false"
        class="boq-sidebar-overlay lg:hidden"
        aria-hidden="true"
    ></div>

    {{-- ============================ SIDEBAR ============================ --}}
    <aside
        id="app-sidebar"
        class="boq-sidebar"
        :class="{ 'is-open': sidebarOpen }"
        aria-label="{{ __('Main navigation') }}"
    >
        <div class="boq-sidebar-brand">
            <a href="{{ route('dashboard') }}" class="boq-brand" title="{{ $siteName }}">
                <span class="boq-brand-mark">
                    @if($siteLogo)
                        <img src="{{ asset('storage/'.$siteLogo) }}" alt="">
                    @else
                        <i class="fas fa-file-invoice-dollar" aria-hidden="true"></i>
                    @endif
                </span>

                <span class="boq-brand-text">
                    <span class="boq-brand-name">{{ $siteName }}</span>
                    <span class="boq-brand-tagline">{{ __('Cost intelligence') }}</span>
                </span>
            </a>

            <button
                type="button"
                @click="sidebarOpen = false"
                class="boq-topbar-btn lg:hidden"
                aria-label="{{ __('Close navigation') }}"
            >
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </button>
        </div>

        <nav class="boq-sidebar-nav">
            @foreach($navGroups as $group)
                @php
                    $groupActive = collect($group['items'])->contains('active', true);
                @endphp

                <div
                    class="boq-nav-group"
                    @if(! empty($group['admin']))
                        x-data="{ groupOpen: {{ $groupActive || request()->is('admin*') ? 'true' : 'false' }} }"
                    @endif
                >
                    @if(! empty($group['admin']))
                        <button
                            type="button"
                            class="boq-nav-heading"
                            @click="groupOpen = ! groupOpen"
                            :aria-expanded="groupOpen.toString()"
                            aria-controls="nav-group-{{ $loop->index }}"
                        >
                            <span><i class="fas fa-shield-halved mr-1" aria-hidden="true"></i> {{ __($group['label']) }}</span>
                            <i class="fas fa-chevron-down text-[9px] transition-transform" :class="groupOpen ? 'rotate-180' : ''" aria-hidden="true"></i>
                        </button>
                    @else
                        <p class="boq-nav-heading"><span>{{ __($group['label']) }}</span></p>
                    @endif

                    <ul
                        id="nav-group-{{ $loop->index }}"
                        class="boq-nav-list"
                        @if(! empty($group['admin'])) x-show="groupOpen || collapsed" x-cloak @endif
                    >
                        @foreach($group['items'] as $link)
                            <li>
                                <a
                                    href="{{ $link['url'] }}"
                                    class="boq-nav-item {{ $link['active'] ? 'is-active' : '' }}"
                                    @if($link['active']) aria-current="page" @endif
                                    :title="collapsed ? @js(__($link['label'])) : null"
                                   
                                >
                                    <span class="boq-nav-icon"><i class="fas {{ $link['icon'] }}" aria-hidden="true"></i></span>
                                    <span class="boq-nav-label">{{ __($link['label']) }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        <div class="boq-sidebar-footer hidden lg:block">
            <button
                type="button"
                class="boq-nav-item w-full border-0 bg-transparent"
                @click="toggleCollapsed()"
                :aria-pressed="collapsed.toString()"
                :title="collapsed ? @js(__('Expand sidebar')) : null"
            >
                <span class="boq-nav-icon">
                    <i class="fas" :class="collapsed ? 'fa-angles-right' : 'fa-angles-left'" aria-hidden="true"></i>
                </span>
                <span class="boq-nav-label">{{ __('Collapse sidebar') }}</span>
            </button>
        </div>
    </aside>

    {{-- ============================ MAIN ============================ --}}
    <div class="boq-main">

        <header class="boq-topbar">
            <button
                type="button"
                @click="sidebarOpen = true"
                class="boq-topbar-btn lg:hidden"
                aria-label="{{ __('Open navigation') }}"
                aria-controls="app-sidebar"
                :aria-expanded="sidebarOpen.toString()"
            >
                <i class="fas fa-bars" aria-hidden="true"></i>
            </button>

            <div class="boq-topbar-title">
                <nav aria-label="{{ __('Breadcrumb') }}">
                    <ol class="boq-breadcrumb">
                        @if($currentGroup)
                            <li class="hidden sm:block">{{ __($currentGroup['label']) }}</li>
                            <li class="boq-breadcrumb-sep hidden sm:block" aria-hidden="true"><i class="fas fa-chevron-right"></i></li>
                        @endif

                        @if($pageLabel && $subPage && $currentItem)
                            <li class="min-w-0 truncate"><a href="{{ $currentItem['url'] }}">{{ __($pageLabel) }}</a></li>
                            <li class="boq-breadcrumb-sep" aria-hidden="true"><i class="fas fa-chevron-right"></i></li>
                            <li class="boq-breadcrumb-current" aria-current="page">{{ __($subPage) }}</li>
                        @elseif($pageLabel)
                            <li class="boq-breadcrumb-current" aria-current="page">{{ __($pageLabel) }}</li>
                        @else
                            <li class="boq-breadcrumb-current">{{ $siteName }}</li>
                        @endif
                    </ol>
                </nav>
            </div>

            <div class="boq-topbar-actions">
                {{-- Shown only when the browser can install the app (or on iPhone/iPad). --}}
                <button type="button" data-pwa-install hidden class="boq-pwa-install-btn" title="{{ __('Install the app on this device') }}">
                    <i class="fas fa-download" aria-hidden="true"></i>
                    <span>{{ __('Install app') }}</span>
                </button>

                <livewire:shell.notifications-menu />

                {{-- User menu --}}
                <div
                    class="relative"
                    x-data="{ open: false }"
                    x-on:click.outside="open = false"
                    x-on:keydown.escape.stop="open = false; $refs.trigger.focus()"
                >
                    <button
                        type="button"
                        x-ref="trigger"
                        class="boq-user-trigger"
                        @click="open = ! open"
                        :aria-expanded="open.toString()"
                        aria-haspopup="menu"
                        aria-controls="user-menu"
                    >
                        <span class="boq-avatar">
                            @if($authUser->avatar_url)
                                <img src="{{ $authUser->avatar_url }}" alt="">
                            @else
                                {{ $initials }}
                            @endif
                        </span>
                        <span class="boq-user-trigger-name hidden md:inline">{{ $authUser->name }}</span>
                        <i class="fas fa-chevron-down hidden text-[10px] text-slate-400 md:inline" aria-hidden="true"></i>
                        <span class="sr-only">{{ __('Open user menu') }}</span>
                    </button>

                    <div
                        id="user-menu"
                        x-show="open"
                        x-cloak
                        x-transition.origin.top.right
                        class="boq-menu w-64"
                        role="menu"
                    >
                        <div class="boq-menu-header flex items-center gap-3">
                            <span class="boq-avatar boq-avatar-lg">
                                @if($authUser->avatar_url)
                                    <img src="{{ $authUser->avatar_url }}" alt="">
                                @else
                                    {{ $initials }}
                                @endif
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold text-slate-900">{{ $authUser->name }}</span>
                                <span class="block truncate text-xs text-slate-500">{{ $authUser->email }}</span>
                            </span>
                        </div>

                        <a href="{{ route('profile.edit') }}" class="boq-menu-item" role="menuitem">
                            <i class="fas fa-user" aria-hidden="true"></i> {{ __('Update profile') }}
                        </a>

                        <a href="{{ route('profile.edit', ['tab' => 'company']) }}" class="boq-menu-item" role="menuitem">
                            <i class="fas fa-building" aria-hidden="true"></i> {{ __('Company Profile') }}
                        </a>

                        @if($authUser->hasPermission('subscriptions.view'))
                            <a href="{{ route('subscriptions.index') }}" class="boq-menu-item" role="menuitem">
                                <i class="fas fa-credit-card" aria-hidden="true"></i> {{ __('Subscriptions') }}
                            </a>
                        @endif

                        <a href="{{ route('plans.index') }}" class="boq-menu-item" role="menuitem">
                            <i class="fas fa-layer-group" aria-hidden="true"></i> {{ __('Plans') }}
                        </a>

                        <div class="boq-menu-sep" role="separator"></div>

                        {{-- Quick light/dark switch: applied at once, saved in the background. --}}
                        <form
                            method="POST"
                            action="{{ route('preferences.theme') }}"
                            x-data="{
                                isDark: @js($uiPrefs['theme'] === 'dark'),
                                effectiveDark() {
                                    const theme = document.documentElement.dataset.theme;

                                    return theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                                },
                                init() {
                                    const sync = () => { this.isDark = this.effectiveDark(); };
                                    sync();
                                    {{-- Stay right when Preferences previews a theme or the OS switches. --}}
                                    new MutationObserver(sync).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
                                    window.matchMedia('(prefers-color-scheme: dark)').addEventListener?.('change', sync);
                                },
                                toggle() {
                                    const theme = this.effectiveDark() ? 'light' : 'dark';
                                    document.documentElement.dataset.theme = theme;
                                    this.isDark = theme === 'dark';

                                    fetch(this.$el.action, {
                                        method: 'POST',
                                        headers: {
                                            'Accept': 'application/json',
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                                            'X-Requested-With': 'XMLHttpRequest',
                                        },
                                        body: JSON.stringify({ theme }),
                                    }).catch(() => {});
                                },
                            }"
                            x-on:submit.prevent="toggle()"
                        >
                            @csrf
                            <input type="hidden" name="theme" value="{{ $uiPrefs['theme'] === 'dark' ? 'light' : 'dark' }}">
                            <button type="submit" class="boq-menu-item" role="menuitem" data-theme-toggle>
                                <i class="fas" :class="isDark ? 'fa-sun' : 'fa-moon'" aria-hidden="true"></i>
                                <span x-text="isDark ? @js(__('Light mode')) : @js(__('Dark mode'))">{{ $uiPrefs['theme'] === 'dark' ? __('Light mode') : __('Dark mode') }}</span>
                            </button>
                        </form>

                        <a href="{{ route('preferences') }}" class="boq-menu-item" role="menuitem">
                            <i class="fas fa-palette" aria-hidden="true"></i> {{ __('Preferences') }}
                        </a>

                        <div class="boq-menu-sep" role="separator"></div>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="boq-menu-item is-danger" role="menuitem">
                                <i class="fas fa-right-from-bracket" aria-hidden="true"></i> {{ __('Log out') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main id="main-content" class="boq-content" tabindex="-1">
            {{ $slot }}
        </main>

        <footer class="boq-footer">
            &copy; {{ date('Y') }} {{ $siteName }}
            <span class="mx-1.5" aria-hidden="true">&middot;</span>
            <a href="{{ route('legal.privacy') }}">{{ __('Privacy Policy') }}</a>
            <span class="mx-1.5" aria-hidden="true">&middot;</span>
            <a href="{{ route('legal.terms') }}">{{ __('Terms of Use') }}</a>
        </footer>
    </div>
</div>

@include('pwa.install-help')

@livewireScripts

@stack('scripts')

</body>
</html>
