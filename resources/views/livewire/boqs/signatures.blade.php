<div>
@if($visible)
    @php
        $canEdit = $this->canEdit;
        $fileSize = function (int $bytes): string {
            return $bytes >= 1048576
                ? \App\Support\Format::number($bytes / 1048576, 1).' MB'
                : \App\Support\Format::number(max(1, $bytes / 1024), 0).' KB';
        };
    @endphp

    <div class="boq-page-stack" id="boq-signatures">
        <x-ui.flash :keys="['status', 'error']" />

        {{-- ======================= SIGN-OFF ======================= --}}
        <x-ui.card :title="__('Signatures')" icon="fa-signature" :subtitle="__('Signatures appear in the sign-off block at the end of the PDF.')">
            <div class="boq-signature-grid">
                @foreach($roles as $role => $roleLabel)
                    @php
                        $signature = $signed->get($role);
                        $isClient = $role === \App\Models\BoqSignature::ROLE_CLIENT;
                    @endphp

                    <div class="boq-signature-slot" wire:key="signature-slot-{{ $role }}">
                        <div class="boq-signature-slot-head">
                            <span class="boq-signature-slot-title">{{ $roleLabel }}</span>

                            @if($signature)
                                <x-ui.badge color="success" icon="fa-circle-check">{{ __('Signed') }}</x-ui.badge>
                            @elseif($isClient && $link)
                                <x-ui.badge color="info" icon="fa-paper-plane">{{ __('Awaiting client') }}</x-ui.badge>
                            @else
                                <x-ui.badge>{{ __('Not signed') }}</x-ui.badge>
                            @endif
                        </div>

                        @if($signature)
                            <div class="boq-signature-preview">
                                <img src="{{ $signature->image_url }}" alt="{{ __('Signature of :name', ['name' => $signature->name]) }}">
                            </div>

                            <dl class="boq-signature-meta">
                                <dt>{{ __('Name') }}</dt>
                                <dd>{{ $signature->name }}</dd>
                                <dt>{{ __('Title') }}</dt>
                                <dd>{{ $signature->title ?: '—' }}</dd>
                                <dt>{{ __('Date') }}</dt>
                                <dd><x-date :value="$signature->signed_at" /></dd>
                                <dt>{{ __('Method') }}</dt>
                                <dd>
                                    @if($signature->signedRemotely())
                                        {{ __('Signed through the secure link') }}
                                    @elseif($signature->method === \App\Models\BoqSignature::METHOD_UPLOADED)
                                        {{ __('Uploaded image') }}
                                    @else
                                        {{ __('Drawn') }}
                                    @endif
                                </dd>
                            </dl>
                        @else
                            <div class="boq-signature-preview is-empty">
                                {{ $isClient ? __('Waiting for the client to sign.') : __('Not signed yet.') }}
                            </div>
                        @endif

                        @if($canEdit)
                            <div class="flex flex-wrap gap-2">
                                @if($isClient)
                                    <x-ui.button size="sm" icon="fa-paper-plane" wire:click="openRequest" loading="openRequest">
                                        {{ $link ? __('Share signing link') : __('Request client signature') }}
                                    </x-ui.button>
                                    <x-ui.button size="sm" variant="secondary" icon="fa-hand-point-right" wire:click="openCapture('client')">
                                        {{ __('Client signs here') }}
                                    </x-ui.button>
                                @else
                                    <x-ui.button size="sm" icon="fa-pen-nib" wire:click="openCapture('preparer')">
                                        {{ $signature ? __('Replace signature') : __('Add my signature') }}
                                    </x-ui.button>
                                    @if($savedSignatureUrl)
                                        <x-ui.button size="sm" variant="secondary" icon="fa-stamp" wire:click="useSavedSignature" loading="useSavedSignature">
                                            {{ __('Use my saved signature') }}
                                        </x-ui.button>
                                    @endif
                                @endif

                                @if($signature)
                                    <x-ui.button size="sm" variant="ghost" icon="fa-trash" class="text-red-600" wire:click="confirmRemoveSignature('{{ $role }}')">
                                        {{ __('Remove') }}
                                    </x-ui.button>
                                @endif
                            </div>

                            @if($isClient && $link)
                                <p class="boq-field-help">
                                    <i class="fas fa-clock" aria-hidden="true"></i>
                                    {{ __('Signing link active until :date.', ['date' => \App\Support\Format::date($link->expires_at)]) }}
                                </p>
                            @endif

                            @if(! $isClient && ! $savedSignatureUrl)
                                <p class="boq-field-help">
                                    {{ __('Tip: save a signature in your profile to add it to any BOQ in one click.') }}
                                    <a href="{{ route('profile.edit', ['tab' => 'signature']) }}" class="boq-link-button">{{ __('Save a signature') }}</a>
                                </p>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
        </x-ui.card>

        {{-- ======================= SIGNED COPIES ======================= --}}
        <x-ui.card :title="__('Signed copies')" icon="fa-file-signature" :subtitle="__('Keep a scan, photo or PDF of the document signed on paper. Files are private to your team.')">
            @if($canEdit)
                <form wire:submit="uploadSignedDocument" class="mb-4 flex flex-col gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <input id="signed-document-file" type="file" wire:model="signedDocument" accept="application/pdf,image/png,image/jpeg,image/webp" class="peer sr-only">
                        <label for="signed-document-file" class="boq-btn-secondary cursor-pointer peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand-600">
                            <i class="fas fa-paperclip" aria-hidden="true"></i>
                            {{ __('Choose file') }}
                        </label>

                        @if($signedDocument && method_exists($signedDocument, 'getClientOriginalName'))
                            <span class="min-w-0 truncate text-sm text-slate-600">{{ $signedDocument->getClientOriginalName() }}</span>
                        @endif

                        <x-ui.button type="submit" icon="fa-upload" loading="uploadSignedDocument,signedDocument">{{ __('Upload signed copy') }}</x-ui.button>
                    </div>
                    <p wire:loading wire:target="signedDocument" class="boq-field-help">{{ __('Uploading...') }}</p>
                    @error('signedDocument') <p class="boq-field-error" role="alert"><i class="fas fa-circle-exclamation mt-0.5" aria-hidden="true"></i> <span>{{ $message }}</span></p> @enderror
                    <p class="boq-field-help">{{ __('PDF or photo (PNG, JPG, WebP), up to 20 MB. Upload again to keep a newer version.') }}</p>
                </form>
            @endif

            @if($documents->isEmpty())
                <x-ui.empty-state icon="fa-file-circle-check" :title="__('No signed copies yet')" :description="__('Print the PDF, have it signed, then upload the scan or photo here.')" />
            @else
                <div class="boq-record-list">
                    @foreach($documents as $document)
                        <div class="boq-record" wire:key="signed-document-{{ $document->id }}">
                            <span class="boq-record-icon" aria-hidden="true">
                                <i class="fas {{ $document->isPdf() ? 'fa-file-pdf' : 'fa-file-image' }}"></i>
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-slate-900">
                                    {{ $document->original_name }}
                                    @if($loop->first && $documents->count() > 1)
                                        <x-ui.badge color="brand" class="ml-1">{{ __('Latest') }}</x-ui.badge>
                                    @endif
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ __('Version :number', ['number' => $documents->count() - $loop->index]) }}
                                    · {{ $fileSize((int) $document->size) }}
                                    · {{ __('Uploaded :date by :name', ['date' => \App\Support\Format::date($document->created_at, true), 'name' => $document->user?->name ?? __('a former user')]) }}
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <x-ui.button size="sm" variant="secondary" icon="fa-download" :href="route('boqs.signed-documents.download', [$boq, $document])">{{ __('Download') }}</x-ui.button>
                                @if($canEdit)
                                    <x-ui.button size="sm" variant="ghost" icon="fa-trash" class="text-red-600" wire:click="confirmDeleteDocument({{ $document->id }})" :aria-label="__('Delete :name', ['name' => $document->original_name])">{{ __('Delete') }}</x-ui.button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>
    </div>

    {{-- ======================= CAPTURE MODAL ======================= --}}
    @if($capturing)
        @php $capturingClient = $capturing === \App\Models\BoqSignature::ROLE_CLIENT; @endphp
        <x-ui.modal
            wire:key="signature-capture-{{ $capturing }}"
            id="signature-capture"
            :title="$capturingClient ? __('Client signs here') : __('Add my signature')"
            :subtitle="$capturingClient ? __('Hand this device to the client to sign.') : __('Draw your signature or upload an image of it.')"
            icon="fa-signature"
            size="lg"
            close="closeCapture"
            submit="saveSignature"
        >
            <div class="space-y-4">
                @if(! $capturingClient && $savedSignatureUrl)
                    <div class="boq-signature-slot">
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="boq-signature-preview min-h-0 w-40 !p-2">
                                <img src="{{ $savedSignatureUrl }}" alt="{{ __('My saved signature') }}" class="!max-h-12">
                            </div>
                            <div class="min-w-0 flex-1 text-sm text-slate-600">{{ __('Use the signature saved in your profile, or draw a new one for this BOQ below.') }}</div>
                            <x-ui.button size="sm" variant="secondary" icon="fa-stamp" wire:click="useSavedSignature" loading="useSavedSignature">{{ __('Use my saved signature') }}</x-ui.button>
                        </div>
                    </div>
                @endif

                <div class="boq-signature-methods" role="tablist" aria-label="{{ __('How to sign') }}">
                    <button type="button" role="tab" wire:click="setMethod('draw')" class="{{ $method === 'draw' ? 'is-active' : '' }}" aria-selected="{{ $method === 'draw' ? 'true' : 'false' }}">
                        <i class="fas fa-pen-nib" aria-hidden="true"></i> {{ __('Draw') }}
                    </button>
                    <button type="button" role="tab" wire:click="setMethod('upload')" class="{{ $method === 'upload' ? 'is-active' : '' }}" aria-selected="{{ $method === 'upload' ? 'true' : 'false' }}">
                        <i class="fas fa-image" aria-hidden="true"></i> {{ __('Upload image') }}
                    </button>
                </div>

                @if($method === 'draw')
                    <div wire:key="signature-method-draw">
                        <x-ui.signature-pad id="capture-signature-data" model="drawnSignature" />
                        @error('drawnSignature') <p class="boq-field-error" role="alert"><i class="fas fa-circle-exclamation mt-0.5" aria-hidden="true"></i> <span>{{ $message }}</span></p> @enderror
                    </div>
                @else
                    <x-ui.field wire:key="signature-method-upload" :label="__('Signature image')" for="capture-signature-file" error="uploadedSignature" :hint="__('PNG, JPG or WebP, up to 2 MB. A photo of a signature on white paper works; the background is removed.')">
                        <input id="capture-signature-file" type="file" wire:model="uploadedSignature" accept="image/png,image/jpeg,image/webp" class="boq-field">
                        <p wire:loading wire:target="uploadedSignature" class="boq-field-help">{{ __('Uploading...') }}</p>
                    </x-ui.field>
                @endif

                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.field :label="__('Full name')" for="capture-name" error="details.name" required class="sm:col-span-1">
                        <input id="capture-name" type="text" wire:model="details.name" maxlength="150" class="boq-field @error('details.name') has-error @enderror" autocomplete="name">
                    </x-ui.field>
                    <x-ui.field :label="__('Title / role')" for="capture-title" error="details.title">
                        <input id="capture-title" type="text" wire:model="details.title" maxlength="150" class="boq-field @error('details.title') has-error @enderror" placeholder="{{ $capturingClient ? __('e.g. Director') : __('e.g. Quantity Surveyor') }}">
                    </x-ui.field>
                    <x-ui.field :label="__('Date')" for="capture-date" error="details.date">
                        <input id="capture-date" type="date" wire:model="details.date" class="boq-field @error('details.date') has-error @enderror">
                    </x-ui.field>
                </div>

                @if($capturingClient)
                    <div>
                        <label class="boq-check">
                            <input type="checkbox" wire:model="approved">
                            {{ __('I approve this Bill of Quantities') }}
                        </label>
                        @error('approved') <p class="boq-field-error" role="alert"><i class="fas fa-circle-exclamation mt-0.5" aria-hidden="true"></i> <span>{{ $message }}</span></p> @enderror
                    </div>
                @endif
            </div>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="closeCapture">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" icon="fa-check" loading="saveSignature">{{ __('Save signature') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- ======================= CLIENT LINK MODAL ======================= --}}
    @if($showRequestModal && $link)
        <x-ui.modal wire:key="signature-request" id="signature-request" :title="__('Request client signature')" :subtitle="__('The client opens the link, reviews the BOQ and signs. The link works once.')" icon="fa-paper-plane" close="closeRequest" submit="sendRequestEmail">
            <div class="space-y-4">
                <x-ui.field :label="__('Signing link')" for="signature-link" :hint="__('Valid until :date. Anyone with the link can sign, so share it only with the client.', ['date' => \App\Support\Format::date($link->expires_at)])">
                    <div class="boq-signature-link" x-data="{ copied: false }">
                        <input id="signature-link" type="text" readonly value="{{ $linkUrl }}" x-ref="link" class="boq-field" x-on:focus="$el.select()">
                        <button type="button" class="boq-btn-secondary" x-on:click="$refs.link.select(); navigator.clipboard?.writeText($refs.link.value).then(() => { copied = true; setTimeout(() => copied = false, 2000) })">
                            <i class="fas" x-bind:class="copied ? 'fa-check' : 'fa-copy'" aria-hidden="true"></i>
                            <span x-text="copied ? @js(__('Copied')) : @js(__('Copy'))">{{ __('Copy') }}</span>
                        </button>
                    </div>
                </x-ui.field>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" class="boq-btn-secondary boq-btn-sm">
                        <i class="fab fa-whatsapp" aria-hidden="true"></i> {{ __('Share on WhatsApp') }}
                    </a>
                    <x-ui.button variant="ghost" size="sm" icon="fa-rotate" wire:click="newLink" loading="newLink">{{ __('New link') }}</x-ui.button>
                    <x-ui.button variant="ghost" size="sm" icon="fa-ban" class="text-red-600" wire:click="cancelLink" loading="cancelLink">{{ __('Cancel link') }}</x-ui.button>
                </div>

                <div class="border-t border-slate-200 pt-4">
                    <p class="mb-3 text-sm font-semibold text-slate-900"><i class="fas fa-envelope" aria-hidden="true"></i> {{ __('Send by email') }}</p>
                    <div class="space-y-3">
                        <x-ui.field :label="__('Client email')" for="signature-request-email" error="requestEmail" required>
                            <input id="signature-request-email" type="email" wire:model="requestEmail" class="boq-field @error('requestEmail') has-error @enderror" placeholder="{{ __('e.g. name@example.com') }}" autocomplete="email">
                        </x-ui.field>
                        <x-ui.field :label="__('Message (optional)')" for="signature-request-message" error="requestMessage">
                            <textarea id="signature-request-message" wire:model="requestMessage" rows="3" class="boq-field boq-textarea" placeholder="{{ __('Add a short note for the recipient...') }}"></textarea>
                        </x-ui.field>
                    </div>
                </div>
            </div>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="closeRequest">{{ __('Close') }}</x-ui.button>
                <x-ui.button type="submit" icon="fa-paper-plane" loading="sendRequestEmail">{{ __('Send email') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- ======================= CONFIRMATIONS ======================= --}}
    @if($confirmingRemoveRole)
        <x-ui.modal wire:key="signature-remove" id="signature-remove" :title="__('Remove signature?')" icon="fa-triangle-exclamation" size="sm" close="$set('confirmingRemoveRole', null)">
            <p class="text-sm text-slate-600">{{ __('The signature is removed from this BOQ and its PDF. This cannot be undone.') }}</p>
            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="$set('confirmingRemoveRole', null)">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button variant="danger" icon="fa-trash" wire:click="removeSignature" loading="removeSignature">{{ __('Remove') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if($confirmingDocumentId)
        <x-ui.modal wire:key="signed-document-delete" id="signed-document-delete" :title="__('Delete signed copy?')" icon="fa-triangle-exclamation" size="sm" close="$set('confirmingDocumentId', null)">
            <p class="text-sm text-slate-600">{{ __('The file is deleted permanently. Other versions are kept.') }}</p>
            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="$set('confirmingDocumentId', null)">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button variant="danger" icon="fa-trash" wire:click="deleteDocument" loading="deleteDocument">{{ __('Delete') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
@endif
</div>
