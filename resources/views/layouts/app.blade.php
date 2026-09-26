<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    <meta
        name="theme-color"
        content="#05645b"
    >

    <meta
        name="description"
        content="{{ $metaDescription ?? 'BOQ System for project cost planning, BOQ management and market pricing.' }}"
    >

    <meta
        name="robots"
        content="noindex,nofollow"
    >

    <title>
        {{ isset($title) ? $title.' | BOQ System' : 'BOQ System' }}
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    >

    @stack('styles')

    @livewireStyles
</head>

<body class="boq-app-shell font-sans antialiased">

@php
    $authUser = Auth::user();

    $navigation = collect([
        [
            'label' => 'Dashboard',
            'url' => url('/dashboard'),
            'active' => request()->is('dashboard'),
            'show' => true,
            'icon' => 'fa-chart-line',
        ],
        [
            'label' => 'Projects',
            'url' => url('/projects'),
            'active' => request()->is('projects*'),
            'show' => $authUser->hasPermission('projects.view'),
            'icon' => 'fa-folder-open',
        ],
        [
            'label' => 'BOQs',
            'url' => url('/boqs'),
            'active' => request()->is('boqs*'),
            'show' => $authUser->hasPermission('boq.view'),
            'icon' => 'fa-file-invoice-dollar',
        ],
      [
    'label' => 'Hardware & Factory Prices',
    'url' => url('/hardware-prices'),
    'active' => request()->is('hardware-prices*'),
    'show' => $authUser->hasPermission('hardware-prices.view'),
    'icon' => 'fa-tags',
],
        [
            'label' => 'Plans',
            'url' => url('/plans'),
            'active' => request()->is('plans*'),
            'show' => true,
            'icon' => 'fa-layer-group',
        ],
        [
            'label' => 'Subscriptions',
            'url' => url('/subscriptions'),
            'active' => request()->is('subscriptions*'),
            'show' => $authUser->hasPermission('subscriptions.view'),
            'icon' => 'fa-credit-card',
        ],
        [
            'label' => 'Administration',
            'url' => url('/admin'),
            'active' => request()->is('admin*'),
            'show' => $authUser->isSuperAdmin(),
            'icon' => 'fa-shield-halved',
        ],
    ])
        ->where('show', true)
        ->values()
        ->all();
@endphp

<div
    x-data="{ sidebarOpen: false }"
    class="min-h-screen bg-slate-100"
>
    <div
        x-show="sidebarOpen"
        x-cloak
        @click="sidebarOpen = false"
        class="fixed inset-0 z-40 bg-slate-950/50 backdrop-blur-sm lg:hidden"
    ></div>

    <aside
        x-cloak
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col bg-slate-950 text-white transition-transform duration-200 lg:translate-x-0"
    >

        <div
            class="flex h-20 items-center justify-between border-b border-white/10 px-6"
        >
            <a
                href="{{ url('/dashboard') }}"
                class="flex items-center gap-3"
            >
                <span
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#05645b] shadow-lg"
                >
                    <i class="fas fa-file-invoice-dollar"></i>
                </span>

                <span>
                    <span
                        class="block text-base font-bold tracking-wide"
                    >
                        BOQ System
                    </span>

                    <span
                        class="block text-xs text-slate-400"
                    >
                        Cost intelligence
                    </span>
                </span>
            </a>

            <button
                type="button"
                @click="sidebarOpen = false"
                class="rounded-lg p-2 text-slate-400 hover:bg-white/10 hover:text-white lg:hidden"
                aria-label="Close navigation"
            >
                <i class="fas fa-xmark"></i>
            </button>
        </div>

        <nav
            class="flex-1 space-y-1 overflow-y-auto px-4 py-6"
        >
            <p
                class="mb-3 px-3 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500"
            >
                Workspace
            </p>

            @foreach($navigation as $item)

                <a
                    href="{{ $item['url'] }}"
                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition
                    {{
                        $item['active']
                            ? 'bg-[#05645b] text-white shadow-lg'
                            : 'text-slate-300 hover:bg-white/10 hover:text-white'
                    }}"
                >
                    <span
                        class="flex h-7 w-7 items-center justify-center rounded-lg
                        {{
                            $item['active']
                                ? 'bg-white/15 text-white'
                                : 'bg-white/5 text-slate-400'
                        }}"
                    >
                        <i class="fas {{ $item['icon'] }}"></i>
                    </span>

                    <span>
                        {{ $item['label'] }}
                    </span>
                </a>

            @endforeach
        </nav>

        <div
            class="border-t border-white/10 p-4"
        >
            <a
                href="{{ route('profile.edit') }}"
                class="flex items-center gap-3 rounded-xl p-3 transition
                {{
                    request()->is('profile')
                        ? 'bg-white/10'
                        : 'hover:bg-white/10'
                }}"
            >
                <span
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#d7df21] text-sm font-bold text-slate-950"
                >
                    {{
                        strtoupper(
                            mb_substr(
                                Auth::user()->name,
                                0,
                                1
                            )
                        )
                    }}
                </span>

                <span
                    class="min-w-0 flex-1"
                >
                    <span
                        class="block truncate text-sm font-semibold"
                    >
                        {{ Auth::user()->name }}
                    </span>

                    <span
                        class="block truncate text-xs text-slate-400"
                    >
                        Update profile
                    </span>
                </span>

                <i
                    class="fas fa-chevron-right text-xs text-slate-500"
                ></i>
            </a>

            <form
                method="POST"
                action="{{ route('logout') }}"
                class="mt-2"
            >
                @csrf

                <button
                    type="submit"
                    class="w-full rounded-xl px-3 py-2 text-left text-sm font-medium text-slate-400 transition hover:bg-white/10 hover:text-white"
                >
                    <i class="fas fa-right-from-bracket mr-2"></i>
                    Log out
                </button>
            </form>
        </div>

    </aside>

    <div
        class="min-h-screen lg:pl-72"
    >

        <header
            class="sticky top-0 z-30 flex h-16 items-center border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6 lg:hidden"
        >
            <button
                type="button"
                @click="sidebarOpen = true"
                class="rounded-lg p-2 text-slate-600 hover:bg-slate-100"
                aria-label="Open navigation"
            >
                <i class="fas fa-bars"></i>
            </button>

            <span
                class="ml-3 font-bold text-slate-900"
            >
                BOQ System
            </span>
        </header>

        <main
            class="px-4 py-6 sm:px-6 sm:py-8 xl:px-10"
        >
            <div
                class="mx-auto max-w-7xl"
            >
                {{ $slot }}
            </div>
        </main>

    </div>
</div>

@livewireScripts

@stack('scripts')

</body>
</html>