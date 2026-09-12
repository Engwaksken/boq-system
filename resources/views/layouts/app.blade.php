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
    <div class="min-h-screen bg-gray-100">
        {{-- Desktop + Mobile Nav --}}
        <nav x-data="{ mobileOpen: false, userMenuOpen: false }" class="bg-white border-b border-gray-200 sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    {{-- Left: Brand + Desktop Links --}}
                    <div class="flex items-center">
                        <a href="{{ url('/dashboard') }}" class="flex items-center gap-2 shrink-0">
                            <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-600">
                                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                                </svg>
                            </span>
                            <span class="text-lg font-bold text-gray-900 hidden sm:inline">BOQ System</span>
                        </a>
                        <div class="hidden md:flex md:ml-8 md:items-center md:space-x-1">
                            <a href="{{ url('/dashboard') }}" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md transition {{ request()->is('dashboard') ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                                Dashboard
                            </a>
                            <a href="{{ url('/projects') }}" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md transition {{ request()->is('projects*') ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                                Projects
                            </a>
                            <a href="{{ url('/boqs') }}" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md transition {{ request()->is('boqs*') ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                                BOQs
                            </a>
                            <a href="{{ url('/hardware-prices') }}" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md transition {{ request()->is('hardware-prices*') ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                                Hardware Prices
                            </a>
                            <a href="{{ url('/plans') }}" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md transition {{ request()->is('plans*') ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                                Plans
                            </a>
                            <a href="{{ url('/subscriptions') }}" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md transition {{ request()->is('subscriptions*') ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                                Subscriptions
                            </a>
                        </div>
                    </div>

                    {{-- Right: Desktop User Dropdown --}}
                    <div class="hidden md:flex md:items-center md:ml-6">
                        <div class="relative" @click.outside="userMenuOpen = false">
                            <button @click="userMenuOpen = !userMenuOpen" type="button" class="flex items-center gap-2 px-3 py-1.5 rounded-lg hover:bg-gray-100 transition text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                <span class="flex items-center justify-center w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold">{{ strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}</span>
                                <span class="font-medium text-gray-700">{{ Auth::user()->name }}</span>
                                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div x-show="userMenuOpen" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100" x-transition:leave-end="transform opacity-0 scale-95" x-cloak
                                 class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50">
                                <div class="px-4 py-2 border-b border-gray-100">
                                    <p class="text-sm font-medium text-gray-900">{{ Auth::user()->name }}</p>
                                    <p class="text-xs text-gray-500 truncate">{{ Auth::user()->email }}</p>
                                </div>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition">
                                        Log Out
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    {{-- Mobile Hamburger --}}
                    <div class="flex items-center md:hidden">
                        <button @click="mobileOpen = !mobileOpen" type="button" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500">
                            <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                            <svg x-show="mobileOpen" x-cloak class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Mobile Menu --}}
            <div x-show="mobileOpen" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1" class="md:hidden border-t border-gray-200 bg-white">
                <div class="px-2 pt-2 pb-3 space-y-1">
                    <a href="{{ url('/dashboard') }}" class="block px-3 py-2 rounded-md text-base font-medium {{ request()->is('dashboard') ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">Dashboard</a>
                    <a href="{{ url('/projects') }}" class="block px-3 py-2 rounded-md text-base font-medium {{ request()->is('projects*') ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">Projects</a>
                    <a href="{{ url('/boqs') }}" class="block px-3 py-2 rounded-md text-base font-medium {{ request()->is('boqs*') ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">BOQs</a>
                    <a href="{{ url('/hardware-prices') }}" class="block px-3 py-2 rounded-md text-base font-medium {{ request()->is('hardware-prices*') ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">Hardware Prices</a>
                    <a href="{{ url('/plans') }}" class="block px-3 py-2 rounded-md text-base font-medium {{ request()->is('plans*') ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">Plans</a>
                    <a href="{{ url('/subscriptions') }}" class="block px-3 py-2 rounded-md text-base font-medium {{ request()->is('subscriptions*') ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">Subscriptions</a>
                </div>
                <div class="border-t border-gray-200 pt-4 pb-3">
                    <div class="px-4 flex items-center gap-3">
                        <span class="flex items-center justify-center w-10 h-10 rounded-full bg-indigo-100 text-indigo-700 text-sm font-bold">{{ strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}</span>
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ Auth::user()->name }}</p>
                            <p class="text-xs text-gray-500">{{ Auth::user()->email }}</p>
                        </div>
                    </div>
                    <div class="mt-3 px-2">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full text-left px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition">Log Out</button>
                        </form>
                    </div>
                </div>
            </div>
        </nav>

        {{-- Main Content --}}
        <main class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                {{ $slot }}
            </div>
        </main>

        {{-- Footer --}}
        <footer class="border-t border-gray-200 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <p class="text-center text-sm text-gray-500">&copy; {{ date('Y') }} BOQ System. All rights reserved.</p>
            </div>
        </footer>
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>
