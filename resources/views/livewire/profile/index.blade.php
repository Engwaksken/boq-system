<div>
    {{-- Page Header --}}
    <div class="mb-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">
                    My Profile
                </h1>

                <p class="mt-1 text-sm text-slate-500">
                    Manage your account, security, preferences and saved hardware prices.
                </p>
            </div>
        </div>
    </div>

    {{-- Flash Message --}}
    @if(session('status'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-transition
            x-init="setTimeout(() => show = false, 5000)"
            class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
            role="status"
        >
            {{ session('status') }}
        </div>
    @endif

    {{-- Main Profile Card --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

        {{-- Profile Summary --}}
        <div class="border-b border-slate-200 px-6 py-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">

                {{-- Avatar --}}
                <div class="flex h-16 w-16 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xl font-bold text-indigo-700">
                    {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
                </div>

                <div class="min-w-0">
                    <h2 class="truncate text-lg font-semibold text-slate-900">
                        {{ $user->name }}
                    </h2>

                    <p class="truncate text-sm text-slate-500">
                        {{ $user->email }}
                    </p>

                    @if($user->organisation)
                        <p class="mt-1 text-xs text-slate-400">
                            {{ $user->organisation->name }}
                        </p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="border-b border-slate-200">
            <nav
                class="flex overflow-x-auto px-4 sm:px-6"
                aria-label="Profile tabs"
            >
                @foreach($tabs as $tabKey => $tabLabel)
                    <button
                        type="button"
                        wire:click="setActiveTab('{{ $tabKey }}')"
                        wire:key="profile-tab-{{ $tabKey }}"
                        class="
                            relative whitespace-nowrap border-b-2 px-4 py-4
                            text-sm font-medium transition-colors duration-150

                            {{ $activeTab === $tabKey
                                ? 'border-indigo-600 text-indigo-600'
                                : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700'
                            }}
                        "
                    >
                        {{ $tabLabel }}

                        <span
                            wire:loading
                            wire:target="setActiveTab('{{ $tabKey }}')"
                            class="absolute right-0 top-2"
                        >
                            <span class="block h-2 w-2 animate-pulse rounded-full bg-indigo-500"></span>
                        </span>
                    </button>
                @endforeach
            </nav>
        </div>

        {{-- Tab Content --}}
        <div
            class="p-6"
            wire:key="profile-tab-content-{{ $activeTab }}"
        >
            <div
                wire:loading.flex
                wire:target="setActiveTab"
                class="min-h-[180px] items-center justify-center"
            >
                <div class="text-center">
                    <svg
                        class="mx-auto h-7 w-7 animate-spin text-indigo-600"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle
                            class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        ></circle>

                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                        ></path>
                    </svg>

                    <p class="mt-2 text-sm text-slate-500">
                        Loading...
                    </p>
                </div>
            </div>

            <div wire:loading.remove wire:target="setActiveTab">

                @switch($activeTab)

                    @case('overview')
                        @include('livewire.profile.partials.overview')
                        @break

                    @case('security')
                        @include('livewire.profile.partials.security')
                        @break

                    @case('preferences')
                        @include('livewire.profile.partials.preferences')
                        @break

                    @case('notifications')
                        @include('livewire.profile.partials.notifications')
                        @break

                    @case('hardware-bookmarks')
                        @include(
                            'livewire.profile.partials.hardware-bookmarks',
                            [
                                'bookmarkedHardware' => $bookmarkedHardware
                            ]
                        )
                        @break

                    @default
                        <div class="py-12 text-center">
                            <h3 class="text-base font-semibold text-slate-900">
                                Section unavailable
                            </h3>

                            <p class="mt-1 text-sm text-slate-500">
                                This profile section could not be loaded.
                            </p>
                        </div>

                @endswitch
            </div>
        </div>
    </div>

    {{-- Global Modal --}}
    <livewire:components.modal />
</div>