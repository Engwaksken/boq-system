@php
    [$icon, $heading, $text] = match ($reason) {
        'used' => ['fa-circle-check', __('This BOQ has already been signed'), __('The signing link has been used. Contact the sender if you need to sign again.')],
        'expired' => ['fa-clock', __('This signing link has expired'), __('Ask the sender for a new link.')],
        'revoked' => ['fa-ban', __('This signing link is no longer valid'), __('The sender replaced or cancelled it. Ask them for a new link.')],
        default => ['fa-link-slash', __('This signing link is not valid'), __('Check that you opened the full link, or ask the sender for a new one.')],
    };
@endphp

<x-layouts.guest :title="$heading">
    <div class="space-y-4 text-center">
        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-2xl text-slate-500" aria-hidden="true">
            <i class="fas {{ $icon }}"></i>
        </span>
        <h1 class="text-xl font-bold text-slate-900">{{ $heading }}</h1>
        <p class="text-sm text-slate-600">{{ $text }}</p>
        @if($boq && $reason !== 'invalid')
            <p class="text-xs text-slate-500">{{ $boq->name }} · {{ $boq->reference }}</p>
        @endif
    </div>
</x-layouts.guest>
