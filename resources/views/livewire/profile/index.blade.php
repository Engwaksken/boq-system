@php
    $tabIcons = [
        'overview' => 'fa-user',
        'security' => 'fa-shield-halved',
        'preferences' => 'fa-sliders',
        'notifications' => 'fa-bell',
        'hardware-bookmarks' => 'fa-bookmark',
        'company' => 'fa-building',
        'signature' => 'fa-signature',
    ];
    $initials = collect(preg_split('/\s+/', trim((string) ($user->name ?? ''))) ?: [])
        ->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') ?: 'U';
@endphp

<div class="boq-page-stack">
    <x-ui.page-header
        :title="__('My Profile')"
        icon="fa-circle-user"
        :subtitle="__('Manage your account, security, preferences and saved hardware prices.')"
    />

    <x-ui.flash :keys="['status', 'message', 'error']" />

    <div class="boq-panel">
        {{-- Profile summary --}}
        <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-5 sm:flex-row sm:items-center sm:px-6">
            <span class="boq-avatar boq-avatar-xl">
                @if($user->avatar_url)
                    <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}">
                @else
                    {{ $initials }}
                @endif
            </span>

            <div class="min-w-0 flex-1">
                <h2 class="truncate text-lg font-semibold text-slate-900">{{ $user->name }}</h2>
                <p class="truncate text-sm text-slate-500">{{ $user->email }}</p>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @if($user->organisation)
                        <x-ui.badge icon="fa-building">{{ $user->organisation->name }}</x-ui.badge>
                    @endif
                    @foreach($user->roles ?? [] as $role)
                        <x-ui.badge color="brand" icon="fa-user-shield">{{ $role->name }}</x-ui.badge>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Tabs --}}
        <x-ui.tabs :label="__('Profile tabs')">
            @foreach($tabs as $tabKey => $tabLabel)
                <x-ui.tab
                    wire:click="setActiveTab('{{ $tabKey }}')"
                    wire:key="profile-tab-{{ $tabKey }}"
                    :icon="$tabIcons[$tabKey] ?? null"
                    :active="$activeTab === $tabKey"
                >{{ __($tabLabel) }}</x-ui.tab>
            @endforeach
        </x-ui.tabs>

        {{-- Tab content --}}
        <div class="boq-profile-content" wire:key="profile-tab-content-{{ $activeTab }}">
            <div wire:loading.flex wire:target="setActiveTab" class="boq-loading min-h-[180px]">
                <i class="fas fa-spinner fa-spin" aria-hidden="true"></i>
                {{ __('Loading...') }}
            </div>

            <div wire:loading.remove wire:target="setActiveTab">
                @switch($activeTab)
                    @case('overview')
                        @include('livewire.profile.partials.overview')
                        @break

                    @case('company')
                        @include('livewire.profile.partials.company')
                        @break

                    @case('signature')
                        <livewire:profile.signature wire:key="profile-signature" />
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
                        @include('livewire.profile.partials.hardware-bookmarks', ['bookmarkedHardware' => $bookmarkedHardware])
                        @break

                    @default
                        <x-ui.empty-state
                            icon="fa-triangle-exclamation"
                            :title="__('Section unavailable')"
                            :description="__('This profile section could not be loaded.')"
                        />
                @endswitch
            </div>
        </div>
    </div>

    {{-- Global Modal --}}
    <livewire:components.modal />
</div>
