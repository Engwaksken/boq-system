@props(['count' => 0])

@if($count > 0)
    <div {{ $attributes->class('boq-bulk-bar') }} role="region" aria-label="Bulk actions">
        <span class="boq-bulk-count">
            <i class="fas fa-square-check"></i>
            {{ $count }} selected
        </span>

        <div class="boq-bulk-buttons">
            {{ $slot }}
        </div>

        <button type="button" wire:click="clearSelection" class="boq-bulk-clear-btn">
            <i class="fas fa-xmark"></i>
            Clear
        </button>
    </div>
@endif
