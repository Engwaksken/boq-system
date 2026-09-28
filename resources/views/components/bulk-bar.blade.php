@props(['count' => 0])

@if($count > 0)
    <div {{ $attributes->class('boq-bulk-bar') }} role="region" aria-label="{{ __('Bulk actions') }}">
        <span class="boq-bulk-count" aria-live="polite">
            <i class="fas fa-square-check" aria-hidden="true"></i>
            {{ trans_choice(':count selected|:count selected', $count, ['count' => \App\Support\Format::number($count, 0)]) }}
        </span>

        <div class="boq-bulk-buttons">
            {{ $slot }}
        </div>

        <button type="button" wire:click="clearSelection" class="boq-bulk-clear-btn">
            <i class="fas fa-xmark" aria-hidden="true"></i>
            {{ __('Clear') }}
        </button>
    </div>
@endif
