<div class="boq-page-stack">
    <x-ui.page-header
        :title="__('New Project')"
        icon="fa-folder-plus"
        :subtitle="__('Create a new construction project')"
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="fa-arrow-left" :href="route('projects.index')">{{ __('Back') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @include('livewire.projects.partials.form', [
        'submitLabel' => __('Save Project'),
        'cancelUrl' => route('projects.index'),
    ])
</div>
