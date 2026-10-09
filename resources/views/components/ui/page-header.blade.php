{{--
    Page header: title, optional icon/eyebrow/subtitle and an actions slot.

    <x-ui.page-header :title="__('Projects')" icon="fa-folder-open" subtitle="Manage your projects.">
        <x-slot:actions><x-ui.button href="..." icon="fa-plus">New Project</x-ui.button></x-slot:actions>
    </x-ui.page-header>
--}}
@props([
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'eyebrow' => null,
])

<header {{ $attributes->class('boq-page-header') }}>
    <div class="boq-page-header-main">
        @if($eyebrow)
            <p class="boq-page-eyebrow">{{ $eyebrow }}</p>
        @endif

        <h1 class="boq-page-title">
            @if($icon)
                <i class="fas {{ $icon }}" aria-hidden="true"></i>
            @endif
            <span class="min-w-0">{{ $title ?? $heading ?? '' }}</span>
        </h1>

        @if($subtitle)
            <p class="boq-page-subtitle">{{ $subtitle }}</p>
        @endif

        {{ $slot }}
    </div>

    @if(isset($actions) || (isset($__livewire) && method_exists($__livewire, 'exportTables') && $__livewire->canExportTables()))
        <div class="boq-page-actions">
            {{ $actions ?? '' }}
            @if(isset($__livewire) && method_exists($__livewire, 'exportTables') && $__livewire->canExportTables())
                <x-ui.export-buttons />
            @endif
        </div>
    @endif
</header>
