<?php

namespace App\Livewire\Profile;

use App\Services\BoqSignatureService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Profile > Signature: the user's saved default signature, name and title,
 * added to any BOQ with "Use my saved signature".
 */
class Signature extends Component
{
    use WithFileUploads;

    /** draw | upload */
    public string $method = 'draw';

    public string $drawnSignature = '';

    public $uploadedSignature = null;

    public string $name = '';

    public string $title = '';

    public bool $replacing = false;

    public function mount(): void
    {
        $user = Auth::user();
        abort_unless($user, 401);

        $this->name = (string) ($user->signature_name ?: $user->name);
        $this->title = (string) $user->signature_title;
        $this->replacing = ! $user->signature_path;
    }

    public function setMethod(string $method): void
    {
        $this->method = $method === 'upload' ? 'upload' : 'draw';
        $this->drawnSignature = '';
        $this->uploadedSignature = null;
        $this->resetValidation(['drawnSignature', 'uploadedSignature']);
    }

    public function startReplacing(): void
    {
        $this->replacing = true;
        $this->setMethod('draw');
    }

    public function cancelReplacing(): void
    {
        $this->replacing = ! Auth::user()->signature_path;
        $this->drawnSignature = '';
        $this->uploadedSignature = null;
        $this->resetValidation();
    }

    public function updatedUploadedSignature(): void
    {
        $this->validateOnly('uploadedSignature', ['uploadedSignature' => ['required', ...BoqSignatureService::IMAGE_RULES]]);
    }

    public function save(BoqSignatureService $signatures): void
    {
        $user = Auth::user();
        abort_unless($user, 401);

        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'title' => ['nullable', 'string', 'max:150'],
        ];

        if ($this->replacing) {
            $rules += $this->method === 'upload'
                ? ['uploadedSignature' => ['required', ...BoqSignatureService::IMAGE_RULES]]
                : ['drawnSignature' => ['required', 'string', 'max:3000000']];
        }

        $this->validate($rules, [
            'drawnSignature.required' => __('Draw a signature first.'),
            'uploadedSignature.required' => __('Choose a signature image to upload.'),
        ]);

        if (! $this->replacing) {
            $signatures->updateDefaultDetails($user, $this->name, $this->title);
            session()->flash('status', __('Signature details saved.'));

            return;
        }

        $field = $this->method === 'upload' ? 'uploadedSignature' : 'drawnSignature';

        try {
            $signatures->saveDefault($user, $this->method === 'upload' ? $this->uploadedSignature : $this->drawnSignature, $this->name, $this->title);
        } catch (ValidationException $e) {
            $this->addError($field, collect($e->errors())->flatten()->first());

            return;
        }

        $this->replacing = false;
        $this->drawnSignature = '';
        $this->uploadedSignature = null;
        $this->dispatch('signature-pad-clear');
        session()->flash('status', __('Signature saved. Use it on any BOQ with "Use my saved signature".'));
    }

    public function remove(BoqSignatureService $signatures): void
    {
        $user = Auth::user();
        abort_unless($user, 401);

        $signatures->removeDefault($user);
        $this->replacing = true;
        session()->flash('status', __('Saved signature removed.'));
    }

    public function render()
    {
        return view('livewire.profile.signature', [
            'signatureUrl' => Auth::user()?->signatureUrl(),
        ]);
    }
}
