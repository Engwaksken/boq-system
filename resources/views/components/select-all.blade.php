@props(['ids' => [], 'selected' => []])

@php
    $ids = collect($ids)->map(fn ($id) => (string) $id)->all();
    $allSelected = $ids !== [] && array_diff($ids, $selected) === [];
@endphp

<input
    type="checkbox"
    class="boq-checkbox"
    aria-label="Select all on this page"
    @checked($allSelected)
    @disabled($ids === [])
    wire:click="togglePageSelection(@js($ids))"
>
