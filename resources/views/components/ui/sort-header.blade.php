{{--
    Sortable table header cell. Calls the component's sortBy('<field>') action.

    <x-ui.sort-header field="name" :sort-by="$sortBy" :sort-dir="$sortDir">{{ __('Name') }}</x-ui.sort-header>
--}}
@props([
    'field',
    'sortBy' => null,
    'sortDir' => 'asc',
    'action' => 'sortBy',
])

@php
    $active = $sortBy === $field;
@endphp

<th {{ $attributes }} @if($active) aria-sort="{{ $sortDir === 'asc' ? 'ascending' : 'descending' }}" @endif>
    <button type="button" class="boq-sort-th {{ $active ? 'text-slate-900' : '' }}" wire:click="{{ $action }}('{{ $field }}')">
        {{ $slot }}
        <i class="fas {{ $active ? ($sortDir === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down') : 'fa-sort text-slate-300' }} text-[10px]" aria-hidden="true"></i>
    </button>
</th>
