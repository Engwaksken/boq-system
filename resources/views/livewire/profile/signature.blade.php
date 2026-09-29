<div class="space-y-5">
    <x-ui.flash :keys="['status']" />

    <div>
        <h3 class="text-base font-semibold text-slate-900">{{ __('My signature') }}</h3>
        <p class="mt-1 text-sm text-slate-500">{{ __('Save your signature once and add it to any BOQ with "Use my saved signature". You can still sign a BOQ differently.') }}</p>
    </div>

    <form wire:submit="save" class="max-w-2xl space-y-4">
        @if($signatureUrl && ! $replacing)
            <div class="boq-signature-preview">
                <img src="{{ $signatureUrl }}" alt="{{ __('My saved signature') }}">
            </div>
            <div class="flex flex-wrap gap-2">
                <x-ui.button size="sm" variant="secondary" icon="fa-pen-nib" wire:click="startReplacing">{{ __('Replace signature') }}</x-ui.button>
                <x-ui.button size="sm" variant="ghost" icon="fa-trash" class="text-red-600" wire:click="remove" wire:confirm="{{ __('Remove your saved signature?') }}" loading="remove">{{ __('Remove') }}</x-ui.button>
            </div>
        @else
            <div class="boq-signature-methods" role="tablist" aria-label="{{ __('How to sign') }}">
                <button type="button" role="tab" wire:click="setMethod('draw')" class="{{ $method === 'draw' ? 'is-active' : '' }}" aria-selected="{{ $method === 'draw' ? 'true' : 'false' }}">
                    <i class="fas fa-pen-nib" aria-hidden="true"></i> {{ __('Draw') }}
                </button>
                <button type="button" role="tab" wire:click="setMethod('upload')" class="{{ $method === 'upload' ? 'is-active' : '' }}" aria-selected="{{ $method === 'upload' ? 'true' : 'false' }}">
                    <i class="fas fa-image" aria-hidden="true"></i> {{ __('Upload image') }}
                </button>
            </div>

            @if($method === 'draw')
                <div wire:key="profile-signature-draw">
                    <x-ui.signature-pad id="profile-signature-data" model="drawnSignature" />
                    @error('drawnSignature') <p class="boq-field-error" role="alert"><i class="fas fa-circle-exclamation mt-0.5" aria-hidden="true"></i> <span>{{ $message }}</span></p> @enderror
                </div>
            @else
                <x-ui.field wire:key="profile-signature-upload" :label="__('Signature image')" for="profile-signature-file" error="uploadedSignature" :hint="__('PNG, JPG or WebP, up to 2 MB. A photo of a signature on white paper works; the background is removed.')">
                    <input id="profile-signature-file" type="file" wire:model="uploadedSignature" accept="image/png,image/jpeg,image/webp" class="boq-field">
                    <p wire:loading wire:target="uploadedSignature" class="boq-field-help">{{ __('Uploading...') }}</p>
                </x-ui.field>
            @endif
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.field :label="__('Full name')" for="profile-signature-name" error="name" required>
                <input id="profile-signature-name" type="text" wire:model="name" maxlength="150" class="boq-field @error('name') has-error @enderror" autocomplete="name">
            </x-ui.field>
            <x-ui.field :label="__('Title / role')" for="profile-signature-title" error="title">
                <input id="profile-signature-title" type="text" wire:model="title" maxlength="150" class="boq-field @error('title') has-error @enderror" placeholder="{{ __('e.g. Quantity Surveyor') }}">
            </x-ui.field>
        </div>

        <div class="flex flex-wrap gap-2">
            <x-ui.button type="submit" icon="fa-floppy-disk" loading="save">{{ $replacing ? __('Save signature') : __('Save details') }}</x-ui.button>
            @if($replacing && $signatureUrl)
                <x-ui.button variant="secondary" wire:click="cancelReplacing">{{ __('Cancel') }}</x-ui.button>
            @endif
        </div>
    </form>
</div>
