{{--
    Livewire-driven modal dialog. Render it inside an @if on the component's
    "show" property; the component owns open/close state.

    close:  Livewire expression run by the close button and the Escape key,
            e.g. "closeModal" or "$set('showDeleteModal', false)".
    submit: when given, the dialog is a <form wire:submit="...">.
    size:   sm | lg | xl (default is medium).

    <x-ui.modal wire:key="delete-modal" title="Delete project" close="$set('showDelete', false)" size="sm">
        ...body...
        <x-slot:footer>...buttons...</x-slot:footer>
    </x-ui.modal>
--}}
@props([
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'size' => null,
    'close' => null,
    'submit' => null,
    'id' => null,
])

@php
    $dialogId = $id ?: 'modal-'.substr(md5((string) $title.$close.$submit), 0, 8);
    $tag = $submit ? 'form' : 'div';
    $escape = $close ? (str_contains($close, '(') ? $close : $close.'()') : null;
@endphp

<div
    {{ $attributes->only(['wire:key'])->merge(['class' => 'boq-modal-backdrop']) }}
    role="dialog"
    aria-modal="true"
    @if($title) aria-labelledby="{{ $dialogId }}-title" @endif
    x-data
    x-trap.noscroll="true"
    @if($escape) x-on:keydown.escape.window="$wire.{{ $escape }}" @endif
>
    <{{ $tag }}
        @if($submit) wire:submit="{{ $submit }}" @endif
        {{ $attributes->except(['wire:key'])->class(['boq-modal', 'boq-modal-'.$size => $size]) }}
    >
        @if($title || $close || isset($header))
            <div class="boq-modal-head">
                @isset($header)
                    {{ $header }}
                @else
                    <div class="min-w-0">
                        <h2 id="{{ $dialogId }}-title">
                            @if($icon)
                                <i class="fas {{ $icon }}" aria-hidden="true"></i>
                            @endif
                            {{ $title }}
                        </h2>

                        @if($subtitle)
                            <p class="mt-0.5 text-xs text-slate-500">{{ $subtitle }}</p>
                        @endif
                    </div>
                @endisset

                @if($close)
                    <button type="button" wire:click="{{ $close }}" class="boq-modal-close" aria-label="{{ __('Close') }}">
                        <i class="fas fa-xmark" aria-hidden="true"></i>
                    </button>
                @endif
            </div>
        @endif

        <div class="boq-modal-body">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="boq-modal-foot">
                {{ $footer }}
            </div>
        @endisset
    </{{ $tag }}>
</div>
