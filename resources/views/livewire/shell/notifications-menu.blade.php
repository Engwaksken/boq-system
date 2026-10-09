<div
    class="relative"
    x-data="{ open: false }"
    x-on:click.outside="open = false"
    x-on:keydown.escape.stop="open = false; $refs.bell.focus()"
>
    <button
        type="button"
        x-ref="bell"
        class="boq-topbar-btn"
        @click="open = ! open"
        :aria-expanded="open.toString()"
        aria-haspopup="true"
        aria-controls="notifications-menu"
        title="{{ __('Notifications') }}"
    >
        <i class="fas fa-bell" aria-hidden="true"></i>
        <span class="sr-only">{{ __('Notifications') }}</span>

        @if($unread > 0)
            <span class="boq-topbar-dot" aria-hidden="true">{{ $unread > 9 ? '9+' : $unread }}</span>
            <span class="sr-only">({{ $unread }} {{ __('unread') }})</span>
        @endif
    </button>

    <div
        id="notifications-menu"
        x-show="open"
        x-cloak
        x-transition.origin.top.right
        class="boq-menu boq-notifications-menu"
    >
        <div class="boq-menu-header flex items-center justify-between gap-3">
            <span class="text-sm font-semibold text-slate-900">{{ __('Notifications') }}</span>

            @if($unread > 0)
                <button type="button" wire:click="markAllAsRead" class="boq-link-button text-xs">
                    {{ __('Mark all as read') }}
                </button>
            @endif
        </div>

        <div class="max-h-96 overflow-y-auto">
            @forelse($notifications as $notification)
                <button
                    type="button"
                    wire:key="notification-{{ $notification->id }}"
                    @if(! $notification->read_at) wire:click="markAsRead({{ $notification->id }})" @endif
                    class="boq-notification w-full border-0 {{ $notification->read_at ? 'bg-transparent' : 'is-unread' }}"
                >
                    <span class="boq-notification-dot" aria-hidden="true"></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold text-slate-900">{{ $notification->title ?: __('Notification') }}</span>
                        @if($notification->message)
                            <span class="mt-0.5 block text-xs leading-relaxed text-slate-600">{{ $notification->message }}</span>
                        @endif
                        <span class="mt-1 block text-[11px] text-slate-400">
                            <x-date :value="$notification->created_at" time />
                        </span>
                    </span>
                </button>
            @empty
                <div class="px-4 py-8 text-center">
                    <i class="fas fa-bell-slash mb-2 text-xl text-slate-300" aria-hidden="true"></i>
                    <p class="text-sm text-slate-500">{{ __('You are all caught up.') }}</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
