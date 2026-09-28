{{-- Empty state with icon, title, description and an optional actions slot. --}}
@props([
    'icon' => 'fa-inbox',
    'title' => null,
    'description' => null,
])

<div {{ $attributes->class('boq-empty-state') }}>
    <span class="boq-empty-icon" aria-hidden="true">
        <i class="fas {{ $icon }}"></i>
    </span>

    @if($title)
        <p class="boq-empty-title">{{ $title }}</p>
    @endif

    @if($description)
        <p class="boq-empty-description">{{ $description }}</p>
    @endif

    @if($slot->isNotEmpty())
        <div class="boq-empty-action">
            {{ $slot }}
        </div>
    @endif
</div>
