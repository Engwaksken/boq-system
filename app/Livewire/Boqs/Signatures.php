<?php

namespace App\Livewire\Boqs;

use App\Models\Boq;
use App\Models\BoqSignature;
use App\Services\BoqSignatureService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Sign-off and signed records on the BOQ page: the preparer's signature (drawn,
 * uploaded or the saved default), the client's signature (a one-time link
 * or signed here in person) and uploaded copies of the physically signed BOQ.
 *
 * Viewing needs BoqPolicy::view; every change needs BoqPolicy::update.
 */
class Signatures extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $boqId;

    /** Which signature is being captured: preparer | client | null. */
    public ?string $capturing = null;

    /** draw | upload */
    public string $method = 'draw';

    public string $drawnSignature = '';

    public $uploadedSignature = null;

    /** @var array{name: string, title: string, date: string} */
    public array $details = ['name' => '', 'title' => '', 'date' => ''];

    /** The client ticks "I approve this Bill of Quantities" when signing in person. */
    public bool $approved = false;

    public bool $showRequestModal = false;

    public string $requestEmail = '';

    public string $requestMessage = '';

    public $signedDocument = null;

    public ?int $confirmingDocumentId = null;

    public ?string $confirmingRemoveRole = null;

    public function mount(Boq $boq): void
    {
        $this->boqId = $boq->id;
    }

    #[Computed]
    public function boq(): Boq
    {
        return Boq::with('project')->findOrFail($this->boqId);
    }

    #[Computed]
    public function canView(): bool
    {
        return (bool) Auth::user()?->can('view', $this->boq);
    }

    #[Computed]
    public function canEdit(): bool
    {
        return (bool) Auth::user()?->can('update', $this->boq);
    }

    private function authorizeEdit(): Boq
    {
        abort_unless($this->canEdit, 403);

        return $this->boq;
    }

    /* ------------------------------------------------------------------
     | Capturing a signature
     * ------------------------------------------------------------------ */

    public function openCapture(string $role): void
    {
        abort_unless(in_array($role, BoqSignature::ROLES, true), 404);
        $boq = $this->authorizeEdit();
        $user = Auth::user();
        $existing = $boq->signatures()->where('role', $role)->first();

        $this->capturing = $role;
        $this->method = 'draw';
        $this->drawnSignature = '';
        $this->uploadedSignature = null;
        $this->approved = false;
        $this->details = [
            'name' => (string) ($existing?->name ?? ($role === BoqSignature::ROLE_PREPARER ? ($user->signature_name ?: $user->name) : '')),
            'title' => (string) ($existing?->title ?? ($role === BoqSignature::ROLE_PREPARER ? $user->signature_title : '')),
            'date' => now()->toDateString(),
        ];
        $this->resetValidation();
        $this->dispatch('signature-pad-clear');
    }

    public function closeCapture(): void
    {
        $this->capturing = null;
        $this->drawnSignature = '';
        $this->uploadedSignature = null;
        $this->resetValidation();
    }

    public function setMethod(string $method): void
    {
        $this->method = $method === 'upload' ? 'upload' : 'draw';
        $this->drawnSignature = '';
        $this->uploadedSignature = null;
        $this->resetValidation(['drawnSignature', 'uploadedSignature']);
    }

    public function updatedUploadedSignature(): void
    {
        $this->validateOnly('uploadedSignature', ['uploadedSignature' => ['required', ...BoqSignatureService::IMAGE_RULES]]);
    }

    public function saveSignature(BoqSignatureService $signatures): void
    {
        $boq = $this->authorizeEdit();
        abort_unless(in_array($this->capturing, BoqSignature::ROLES, true), 422);

        $rules = BoqSignatureService::detailRules('details.');
        $rules += $this->method === 'upload'
            ? ['uploadedSignature' => ['required', ...BoqSignatureService::IMAGE_RULES]]
            : ['drawnSignature' => ['required', 'string', 'max:3000000']];

        if ($this->capturing === BoqSignature::ROLE_CLIENT) {
            $rules['approved'] = ['accepted'];
        }

        $this->validate($rules, [
            'drawnSignature.required' => __('Draw a signature first.'),
            'uploadedSignature.required' => __('Choose a signature image to upload.'),
            'approved.accepted' => __('The client must tick "I approve this Bill of Quantities".'),
        ], [
            'details.name' => __('name'),
            'details.title' => __('title'),
            'details.date' => __('date'),
        ]);

        $field = $this->method === 'upload' ? 'uploadedSignature' : 'drawnSignature';

        try {
            $signatures->sign(
                $boq,
                $this->capturing,
                $this->details,
                $this->method === 'upload' ? $this->uploadedSignature : $this->drawnSignature,
                Auth::user(),
                request(),
            );
        } catch (ValidationException $e) {
            $this->addError($field, collect($e->errors())->flatten()->first());

            return;
        }

        $role = $this->capturing;
        $this->closeCapture();
        $this->dispatch('signature-pad-clear');
        session()->flash('status', $role === BoqSignature::ROLE_CLIENT
            ? __('Client signature saved. It now appears on the PDF.')
            : __('Your signature was added. It now appears on the PDF.'));
    }

    public function useSavedSignature(BoqSignatureService $signatures): void
    {
        $boq = $this->authorizeEdit();

        try {
            $signatures->signWithSaved($boq, Auth::user(), request());
        } catch (ValidationException $e) {
            session()->flash('error', collect($e->errors())->flatten()->first());

            return;
        }

        $this->closeCapture();
        session()->flash('status', __('Your saved signature was added. It now appears on the PDF.'));
    }

    public function confirmRemoveSignature(string $role): void
    {
        abort_unless(in_array($role, BoqSignature::ROLES, true), 404);
        $this->authorizeEdit();
        $this->confirmingRemoveRole = $role;
    }

    public function removeSignature(BoqSignatureService $signatures): void
    {
        $boq = $this->authorizeEdit();
        $signature = $boq->signatures()->where('role', (string) $this->confirmingRemoveRole)->first();
        $this->confirmingRemoveRole = null;

        if ($signature) {
            $signatures->remove($signature);
            session()->flash('status', __('Signature removed.'));
        }
    }

    /* ------------------------------------------------------------------
     | Client signing link
     * ------------------------------------------------------------------ */

    public function openRequest(BoqSignatureService $signatures): void
    {
        $boq = $this->authorizeEdit();
        $link = $signatures->currentOrNewRequest($boq, Auth::user());

        $this->requestEmail = (string) ($link->email ?? '');
        $this->requestMessage = '';
        $this->resetValidation();
        $this->showRequestModal = true;
    }

    public function closeRequest(): void
    {
        $this->showRequestModal = false;
    }

    public function newLink(BoqSignatureService $signatures): void
    {
        $boq = $this->authorizeEdit();
        $signatures->createRequest($boq, Auth::user());
        session()->flash('status', __('A new signing link was created. Earlier links no longer work.'));
    }

    public function cancelLink(BoqSignatureService $signatures): void
    {
        $boq = $this->authorizeEdit();
        $signatures->revokeRequests($boq);
        $this->showRequestModal = false;
        session()->flash('status', __('The signing link was cancelled.'));
    }

    public function sendRequestEmail(BoqSignatureService $signatures): void
    {
        $boq = $this->authorizeEdit();

        $data = $this->validate([
            'requestEmail' => ['required', 'email', 'max:255'],
            'requestMessage' => ['nullable', 'string', 'max:2000'],
        ], [], ['requestEmail' => __('client email')]);

        $link = $signatures->currentOrNewRequest($boq, Auth::user());

        try {
            $signatures->emailRequest($boq, $link, Auth::user(), $data['requestEmail'], $data['requestMessage'] ?: null);
        } catch (\Throwable $e) {
            report($e);
            $this->addError('requestEmail', __('The email could not be sent. Please try again later.'));

            return;
        }

        $this->showRequestModal = false;
        session()->flash('status', __('Signing link sent to :email.', ['email' => $data['requestEmail']]));
    }

    /* ------------------------------------------------------------------
     | Signed copies (records)
     * ------------------------------------------------------------------ */

    public function uploadSignedDocument(BoqSignatureService $signatures): void
    {
        $boq = $this->authorizeEdit();

        $this->validate(
            ['signedDocument' => ['required', ...BoqSignatureService::DOCUMENT_RULES]],
            ['signedDocument.max' => __('The signed copy may not be larger than 20 MB.')],
            ['signedDocument' => __('signed copy')],
        );

        try {
            $signatures->storeSignedDocument($boq, $this->signedDocument, Auth::user());
        } catch (ValidationException $e) {
            $this->addError('signedDocument', collect($e->errors())->flatten()->first());

            return;
        }

        $this->reset('signedDocument');
        session()->flash('status', __('Signed copy uploaded.'));
    }

    public function confirmDeleteDocument(int $documentId): void
    {
        $boq = $this->authorizeEdit();
        $this->confirmingDocumentId = $boq->signedDocuments()->findOrFail($documentId)->id;
    }

    public function deleteDocument(BoqSignatureService $signatures): void
    {
        $boq = $this->authorizeEdit();
        $document = $boq->signedDocuments()->find($this->confirmingDocumentId);
        $this->confirmingDocumentId = null;

        if ($document) {
            $signatures->deleteSignedDocument($document);
            session()->flash('status', __('Signed copy deleted.'));
        }
    }

    public function render(BoqSignatureService $signatures)
    {
        if (! $this->canView) {
            return view('livewire.boqs.signatures', ['visible' => false]);
        }

        $boq = $this->boq;
        $link = $this->canEdit ? $signatures->activeRequest($boq) : null;
        $user = Auth::user();

        return view('livewire.boqs.signatures', [
            'visible' => true,
            'boq' => $boq,
            'signed' => $boq->signatures()->with('user:id,name')->get()->keyBy('role'),
            'documents' => $boq->signedDocuments()->with('user:id,name')->get(),
            'link' => $link,
            'linkUrl' => $link?->url,
            'whatsappUrl' => $link?->url ? $signatures->whatsappUrl($boq, (string) $link->url) : null,
            'savedSignatureUrl' => $user?->signatureUrl(),
            'roles' => [
                BoqSignature::ROLE_PREPARER => __('Prepared by'),
                BoqSignature::ROLE_CLIENT => __('Client / Approved by'),
            ],
        ]);
    }
}
