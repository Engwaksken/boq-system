<div class="boq-page-stack">
    <x-ui.page-header
        :title="__('New BOQ')"
        icon="fa-file-arrow-up"
        :subtitle="__('Upload a Bill of Quantities file')"
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="fa-arrow-left" :href="route('boqs.index')">{{ __('Back') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.flash />

    @if(! isset($projects) || $projects->isEmpty())
        <x-ui.alert type="warning" :title="__('Create a project first')">
            {{ __('Every BOQ belongs to a project. Create one, then come back to upload your BOQ.') }}
            @if(auth()->user()->hasPermission('projects.create'))
                <div class="mt-2">
                    <x-ui.button size="sm" icon="fa-folder-plus" :href="route('projects.create')">{{ __('New Project') }}</x-ui.button>
                </div>
            @endif
        </x-ui.alert>
    @endif

    <form wire:submit.prevent="save" class="grid gap-5 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2" :title="__('BOQ details')" icon="fa-file-invoice-dollar">
            <div class="boq-form-grid">
                <x-ui.field :label="__('Project')" for="projectId" error="projectId" required>
                    <select id="projectId" wire:model="projectId" class="boq-field @error('projectId') has-error @enderror">
                        <option value="">{{ __('Select a project') }}</option>
                        @foreach($projects ?? [] as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}@if(!empty($project->code)) ({{ $project->code }})@endif</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field :label="__('BOQ Name')" for="name" error="name" :hint="__('Leave blank to use the file name')">
                    <input type="text" id="name" wire:model="name" placeholder="{{ __('e.g. Main building - Phase 1') }}" class="boq-field @error('name') has-error @enderror">
                </x-ui.field>

                <x-ui.field :label="__('BOQ File')" for="file" error="file" required class="boq-form-span-2">
                    <input
                        type="file"
                        id="file"
                        wire:model.live="file"
                        accept=".xlsx,.xlsm,.ods,.csv,.tsv,.txt,.pdf,.jpg,.jpeg,.png,.webp,.gif,.bmp"
                        class="peer sr-only"
                    >

                    <label
                        for="file"
                        class="peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand-600 flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-4 py-8 text-center transition {{ $file ? 'border-brand-300 bg-brand-50' : 'border-slate-300 bg-slate-50 hover:border-brand-300 hover:bg-brand-50/50' }}"
                    >
                        <span class="boq-empty-icon mb-0"><i class="fas fa-cloud-arrow-up" aria-hidden="true"></i></span>
                        <span class="text-sm font-semibold text-slate-800">{{ __('Choose a file to upload') }}</span>
                        <span class="text-xs text-slate-500">{{ __('Excel (.xlsx, .ods), CSV/TSV, PDF or photos (JPG, PNG, WebP). Maximum file size: 20MB.') }}</span>
                    </label>

                    <div wire:loading.flex wire:target="file" class="mt-3 items-center gap-2 rounded-lg bg-brand-50 px-3 py-2 text-sm text-brand-700">
                        <i class="fas fa-spinner fa-spin" aria-hidden="true"></i> {{ __('Uploading file...') }}
                    </div>

                    @if ($file)
                        <div class="mt-3 flex items-center justify-between gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2" wire:loading.remove wire:target="file">
                            <div class="flex min-w-0 items-center gap-2">
                                <i class="fas fa-file-circle-check text-emerald-600" aria-hidden="true"></i>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-emerald-800">{{ __('File selected') }}</p>
                                    <p class="break-all text-xs text-emerald-700">{{ $file->getClientOriginalName() }}</p>
                                </div>
                            </div>

                            <button type="button" wire:click="$set('file', null)" class="boq-link-button text-red-600 hover:text-red-700">
                                <i class="fas fa-xmark" aria-hidden="true"></i> {{ __('Remove') }}
                            </button>
                        </div>
                    @endif
                </x-ui.field>
            </div>

            <x-slot:footer>
                <x-ui.button variant="secondary" :href="route('boqs.index')">{{ __('Cancel') }}</x-ui.button>
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="file,save"
                    class="boq-btn-primary"
                >
                    <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2">
                        <i class="fas fa-upload" aria-hidden="true"></i> {{ __('Upload BOQ') }}
                    </span>
                    <span wire:loading wire:target="save">
                        <i class="fas fa-spinner fa-spin" aria-hidden="true"></i> {{ __('Processing...') }}
                    </span>
                </button>
            </x-slot:footer>
        </x-ui.card>

        <x-ui.card class="self-start" :title="__('What happens next')" icon="fa-list-check">
            <ol class="space-y-4 text-sm text-slate-600">
                <li class="flex gap-3">
                    <span class="boq-badge boq-badge-brand h-6 w-6 shrink-0 justify-center p-0">1</span>
                    <span>{{ __('Spreadsheets are imported straight away. PDFs and photos are read when you press "Generate BOQ".') }}</span>
                </li>
                <li class="flex gap-3">
                    <span class="boq-badge boq-badge-brand h-6 w-6 shrink-0 justify-center p-0">2</span>
                    <span>{{ __('Each item is matched to current hardware and factory prices near your project.') }}</span>
                </li>
                <li class="flex gap-3">
                    <span class="boq-badge boq-badge-brand h-6 w-6 shrink-0 justify-center p-0">3</span>
                    <span>{{ __('Review the suggested rates, then download or share the priced BOQ as a PDF.') }}</span>
                </li>
            </ol>
        </x-ui.card>
    </form>
</div>
