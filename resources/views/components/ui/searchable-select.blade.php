{{--
    Searchable dropdown: a button that opens a panel with a search box and a
    filtered list. Bind it like a select with wire:model.live="...". Options are
    [value => label]; emptyLabel adds a leading choice with an empty value.
--}}
@props([
    'options' => [],
    'value' => '',
    'emptyLabel' => null,
    'placeholder' => '',
    'disabled' => false,
    'searchPlaceholder' => null,
    'id' => null,
])

@php
    $current = (string) ($value ?? '');
    $items = collect($options)->map(fn ($label, $key) => ['value' => (string) $key, 'label' => (string) $label])->values();
    $currentLabel = $items->firstWhere('value', $current)['label']
        ?? ($current === '' && $emptyLabel !== null ? $emptyLabel : $placeholder);

    if ($emptyLabel !== null) {
        $items = $items->prepend(['value' => '', 'label' => (string) $emptyLabel])->values();
    }
@endphp

<div
    class="relative"
    x-data="{
        open: false,
        query: '',
        label: @js($currentLabel),
        options: @js($items),
        filtered() {
            const q = this.query.trim().toLowerCase();

            return this.options.filter((option) => q === '' || option.label.toLowerCase().includes(q));
        },
        choose(option) {
            this.$refs.input.value = option.value;
            this.$refs.input.dispatchEvent(new Event('input', { bubbles: true }));
            this.label = option.label;
            this.open = false;
            this.query = '';
        },
        toggle() {
            this.open = ! this.open;

            if (this.open) {
                this.$nextTick(() => this.$refs.search?.focus());
            }
        },
    }"
    @click.outside="open = false"
    @keydown.escape.stop="open = false"
>
    <input type="hidden" x-ref="input" value="{{ $current }}" {{ $attributes->whereStartsWith('wire:model') }}>

    <button
        type="button"
        @if($id) id="{{ $id }}" @endif
        class="boq-field flex w-full items-center justify-between gap-2 text-left"
        @click="toggle()"
        :aria-expanded="open.toString()"
        aria-haspopup="listbox"
        @disabled($disabled)
    >
        <span class="truncate" x-text="label"></span>
        <i class="fas fa-chevron-down text-xs text-slate-400" aria-hidden="true"></i>
    </button>

    <div
        x-show="open"
        x-cloak
        class="absolute z-30 mt-1 w-full min-w-56 rounded-lg border border-slate-200 bg-white p-2 shadow-lg"
    >
        <input
            x-ref="search"
            x-model="query"
            type="search"
            class="boq-field mb-2"
            placeholder="{{ $searchPlaceholder ?? __('Search...') }}"
            aria-label="{{ $searchPlaceholder ?? __('Search...') }}"
            @keydown.enter.prevent="if (filtered().length) { choose(filtered()[0]) }"
        >

        <ul role="listbox" class="max-h-60 overflow-y-auto">
            <template x-for="option in filtered()" :key="option.value">
                <li>
                    <button
                        type="button"
                        role="option"
                        class="w-full rounded px-3 py-2 text-left text-sm hover:bg-slate-50"
                        :class="option.label === label ? 'font-semibold text-brand-700' : 'text-slate-700'"
                        @click="choose(option)"
                        x-text="option.label"
                    ></button>
                </li>
            </template>
            <li x-show="filtered().length === 0" class="px-3 py-2 text-sm text-slate-500">{{ __('No matches found.') }}</li>
        </ul>
    </div>
</div>
