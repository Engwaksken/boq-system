{{--
    Card whose body can be collapsed from its header. The open/closed choice is
    remembered per browser (key), and every card responds to the window event
    "collapse-cards" (detail: true = expand all, false = collapse all).

    <x-ui.collapse-card key="ratings-top-hardware" title="Top 10" icon="fa-store" subtitle="This month" :open="true" :padded="false">
        ...body...
    </x-ui.collapse-card>
--}}
@props([
    'key',
    'title',
    'icon' => null,
    'subtitle' => null,
    'open' => true,
    'padded' => true,
])

@php($bodyId = 'collapse-'.\Illuminate\Support\Str::slug($key))

<section
    {{ $attributes->class('boq-card') }}
    x-data="{
        open: @js((bool) $open),
        storageKey: @js('boq-collapse:'.$key),
        init() {
            try {
                const saved = localStorage.getItem(this.storageKey);
                if (saved !== null) this.open = saved === '1';
            } catch (e) {}
        },
        set(value) {
            this.open = value;
            try { localStorage.setItem(this.storageKey, value ? '1' : '0'); } catch (e) {}
        },
    }"
    @collapse-cards.window="set($event.detail)"
>
    <div class="boq-card-header" :class="open ? '' : '!border-b-0'">
        <button
            type="button"
            class="flex min-w-0 flex-1 items-center gap-3 text-left"
            @click="set(! open)"
            :aria-expanded="open.toString()"
            aria-controls="{{ $bodyId }}"
        >
            <span class="min-w-0 flex-1">
                <span class="boq-card-title">
                    @if($icon)
                        <i class="fas {{ $icon }}" aria-hidden="true"></i>
                    @endif
                    {{ $title }}
                </span>
                @if($subtitle)
                    <span class="boq-card-subtitle block">{{ $subtitle }}</span>
                @endif
            </span>
            <i class="fas fa-chevron-down shrink-0 text-slate-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" aria-hidden="true"></i>
            <span class="sr-only" x-text="open ? @js(__('Collapse')) : @js(__('Expand'))"></span>
        </button>
    </div>

    <div id="{{ $bodyId }}" x-show="open" x-collapse @if(! $open) x-cloak @endif>
        @if($padded)
            <div class="boq-card-body">{{ $slot }}</div>
        @else
            {{ $slot }}
        @endif
    </div>
</section>
