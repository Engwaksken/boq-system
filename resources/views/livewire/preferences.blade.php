{{--
    Interface preferences. Every change is previewed at once by updating the
    data-* attributes on <html> (and the sidebar state); Save stores them.
--}}
@php
    $themes = [
        'light' => ['fa-sun', __('Light'), __('Bright surfaces, best in daylight.')],
        'dark' => ['fa-moon', __('Dark'), __('Dim surfaces that are easier on the eyes at night.')],
        'system' => ['fa-desktop', __('System'), __('Follow the light or dark setting of your device.')],
    ];

    $densities = [
        'comfortable' => ['fa-table-cells-large', __('Comfortable'), __('Roomy spacing in cards, tables and forms.')],
        'compact' => ['fa-table-cells', __('Compact'), __('Tighter spacing to fit more on the screen.')],
    ];

    $sidebars = [
        'expanded' => ['fa-align-left', __('Expanded'), __('Show icons and labels.')],
        'collapsed' => ['fa-grip-lines-vertical', __('Collapsed'), __('Show icons only, for more room.')],
    ];

    $fonts = [
        'default' => ['fa-font', __('Default'), __('The standard text size.')],
        'large' => ['fa-text-height', __('Large'), __('Larger text across the app.')],
    ];
@endphp

<div
    class="boq-page-stack"
    x-data="{
        preview(key, value) {
            const root = document.documentElement;

            if (key === 'sidebar') {
                root.dataset.sidebar = value;
                window.dispatchEvent(new CustomEvent('boq-sidebar-preference', { detail: value }));

                return;
            }

            root.dataset[key] = value;
        },
    }"
    x-on:preferences-preview.window="Object.entries($event.detail.preferences || {}).forEach(([key, value]) => preview(key, value))"
>
    <x-ui.page-header
        :title="__('Preferences')"
        icon="fa-palette"
        :subtitle="__('Choose how the app looks for you. Changes preview straight away; press Save to keep them.')"
    />

    <x-ui.flash :keys="['success', 'error']" />

    <form wire:submit="save" class="boq-page-stack max-w-4xl">

        {{-- Theme mode --}}
        <x-ui.card :title="__('Theme')" icon="fa-circle-half-stroke" :subtitle="__('Light, dark, or match your device.')">
            <div class="grid gap-2 sm:grid-cols-3" role="radiogroup" aria-label="{{ __('Theme') }}">
                @foreach($themes as $value => [$icon, $label, $hint])
                    <label class="boq-radio-card" wire:key="theme-{{ $value }}">
                        <input type="radio" name="theme" value="{{ $value }}" wire:model="theme" x-on:change="preview('theme', $event.target.value)">
                        <span>
                            <span class="flex items-center gap-2 text-sm font-semibold text-slate-800"><i class="fas {{ $icon }} text-slate-400" aria-hidden="true"></i> {{ $label }}</span>
                            <span class="block text-xs text-slate-500">{{ $hint }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('theme') <p class="boq-field-error" role="alert">{{ $message }}</p> @enderror
        </x-ui.card>

        {{-- Accent colour --}}
        <x-ui.card :title="__('Accent colour')" icon="fa-droplet" :subtitle="__('Used for buttons, links, the active menu item, badges and focus rings.')">
            <div class="boq-swatch-grid" role="radiogroup" aria-label="{{ __('Accent colour') }}">
                @foreach($accents as $value => $label)
                    <label class="boq-swatch-option" wire:key="accent-{{ $value }}">
                        <input type="radio" name="accent" value="{{ $value }}" wire:model="accent" x-on:change="preview('accent', $event.target.value)" class="sr-only">
                        <span class="boq-swatch" data-accent="{{ $value }}" aria-hidden="true"><i class="fas fa-check"></i></span>
                        <span class="boq-swatch-label">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            @error('accent') <p class="boq-field-error" role="alert">{{ $message }}</p> @enderror
        </x-ui.card>

        <div class="grid gap-4 lg:grid-cols-3">
            {{-- Density --}}
            <x-ui.card :title="__('Density')" icon="fa-compress" class="lg:col-span-1">
                <div class="grid gap-2" role="radiogroup" aria-label="{{ __('Density') }}">
                    @foreach($densities as $value => [$icon, $label, $hint])
                        <label class="boq-radio-card" wire:key="density-{{ $value }}">
                            <input type="radio" name="density" value="{{ $value }}" wire:model="density" x-on:change="preview('density', $event.target.value)">
                            <span>
                                <span class="flex items-center gap-2 text-sm font-semibold text-slate-800"><i class="fas {{ $icon }} text-slate-400" aria-hidden="true"></i> {{ $label }}</span>
                                <span class="block text-xs text-slate-500">{{ $hint }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('density') <p class="boq-field-error" role="alert">{{ $message }}</p> @enderror
            </x-ui.card>

            {{-- Sidebar --}}
            <x-ui.card :title="__('Sidebar')" icon="fa-table-columns" class="lg:col-span-1">
                <div class="grid gap-2" role="radiogroup" aria-label="{{ __('Sidebar') }}">
                    @foreach($sidebars as $value => [$icon, $label, $hint])
                        <label class="boq-radio-card" wire:key="sidebar-{{ $value }}">
                            <input type="radio" name="sidebar" value="{{ $value }}" wire:model="sidebar" x-on:change="preview('sidebar', $event.target.value)">
                            <span>
                                <span class="flex items-center gap-2 text-sm font-semibold text-slate-800"><i class="fas {{ $icon }} text-slate-400" aria-hidden="true"></i> {{ $label }}</span>
                                <span class="block text-xs text-slate-500">{{ $hint }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <p class="boq-field-help mt-2">{{ __('Applies on larger screens. On phones the menu always opens from the side.') }}</p>
                @error('sidebar') <p class="boq-field-error" role="alert">{{ $message }}</p> @enderror
            </x-ui.card>

            {{-- Font size --}}
            <x-ui.card :title="__('Font size')" icon="fa-text-height" class="lg:col-span-1">
                <div class="grid gap-2" role="radiogroup" aria-label="{{ __('Font size') }}">
                    @foreach($fonts as $value => [$icon, $label, $hint])
                        <label class="boq-radio-card" wire:key="font-{{ $value }}">
                            <input type="radio" name="font" value="{{ $value }}" wire:model="font" x-on:change="preview('font', $event.target.value)">
                            <span>
                                <span class="flex items-center gap-2 text-sm font-semibold text-slate-800"><i class="fas {{ $icon }} text-slate-400" aria-hidden="true"></i> {{ $label }}</span>
                                <span class="block text-xs text-slate-500">{{ $hint }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('font') <p class="boq-field-error" role="alert">{{ $message }}</p> @enderror
            </x-ui.card>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <x-ui.button variant="secondary" icon="fa-rotate-left" wire:click="restoreDefaults" loading="restoreDefaults">{{ __('Restore defaults') }}</x-ui.button>
            <x-ui.button
                type="submit"
                icon="fa-floppy-disk"
                loading="save"
                x-on:click="try { localStorage.removeItem('boq.sidebar.collapsed'); } catch (e) {}"
            >{{ __('Save preferences') }}</x-ui.button>
        </div>
    </form>
</div>
