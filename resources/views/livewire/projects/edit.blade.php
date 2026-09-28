<div class="boq-page-stack">
    <x-ui.page-header
        :title="__('Edit Project')"
        icon="fa-pen-to-square"
        :subtitle="__('Update project details')"
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="fa-arrow-left" :href="route('projects.show', $project->id)">{{ __('Back') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @include('livewire.projects.partials.form', [
        'submitLabel' => __('Update Project'),
        'cancelUrl' => route('projects.show', $project->id),
    ])
</div>
