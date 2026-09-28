{{-- One price option in the BOQ item "Match a current price" editor. --}}
<div wire:key="{{ $keyPrefix }}-{{ $candidate['id'] }}" class="flex flex-col rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
    <div class="boq-table-title">{{ $candidate['item_name'] }}</div>

    @php
        $details = collect([$candidate['brand'] ?? null, $candidate['specification'] ?? null, $candidate['category'] ?? null])->filter()->join(' · ');
    @endphp

    @if($details !== '')
        <div class="boq-table-subtitle">{{ $details }}</div>
    @endif

    <div class="mt-2 flex flex-wrap gap-x-2 gap-y-1 text-xs text-slate-500">
        @if(! empty($candidate['unit']))
            <span><i class="fas fa-ruler text-slate-400" aria-hidden="true"></i> {{ $candidate['unit'] }}</span>
        @endif
        @if(! empty($candidate['supplier']))
            <span><i class="fas fa-store text-slate-400" aria-hidden="true"></i> {{ $candidate['supplier'] }}</span>
        @endif
        <span><i class="fas fa-location-dot text-slate-400" aria-hidden="true"></i> {{ ($candidate['location'] ?? null) ?: __('No location') }}</span>
    </div>

    <div class="mt-auto flex items-center justify-between gap-2 pt-3">
        <strong class="text-sm tabular-nums text-slate-900">
            {{ $candidate['currency'] ?? '' }} {{ \App\Support\Format::number((float) ($candidate['price'] ?? 0), 2) }}
        </strong>

        <button
            wire:click="selectHardwarePrice({{ $item->id }}, {{ $candidate['id'] }})"
            wire:loading.attr="disabled"
            type="button"
            class="boq-btn-primary boq-btn-sm"
        >
            {{ __('Select') }}
        </button>
    </div>
</div>
