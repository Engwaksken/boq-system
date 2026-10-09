<?php

namespace App\Livewire\Team;

use App\Models\Invitation;
use App\Models\Organisation;
use App\Models\Role;
use App\Services\InvitationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

#[Layout('layouts.app')]
class Index extends Component
{
    use \App\Livewire\Concerns\ExportsTables;
    use WithPagination;
    use WithFileUploads;

    public $bulkFile;

    #[Locked]
    public array $bulkResults = [];

    public function downloadBulkTemplate()
    {
        $this->authorizeManagement();

        return response()->streamDownload(function () {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, ['email', 'role']);
            fputcsv($stream, ['colleague@example.com', 'user']);
            fclose($stream);
        }, 'team-invitations-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function uploadBulkInvitations(\App\Services\BulkInvitationService $service): void
    {
        $this->authorizeManagement();
        $this->validate(['bulkFile' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);
        try {
            $this->bulkResults = $service->import(auth()->user(), $this->bulkFile);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->addError('bulkFile', implode(' ', $exception->errors()['file'] ?? ['The upload is invalid.']));

            return;
        }
        $this->reset('bulkFile');
        $this->resetPage();
        session()->flash('status', __('Bulk invitations processed. Review delivery results below.'));
    }

    public function dismissBulkResults(): void
    {
        $this->reset('bulkResults');
    }

    public string $email = '';
    public ?int $role_id = null;
    public string $expires_at = '';
    public string $acceptToken = '';
    public bool $showForm = false;
    #[Locked]
    public string $createdToken = '';
    #[Locked]
    public bool $invitationEmailSent = false;
    #[Locked]
    public ?int $editingId = null;

    public function boot(): void
    {
        abort_unless(auth()->check(), 401);
    }

    private function canManage(): bool
    {
        $organisation = Organisation::find(auth()->user()->organisation_id);

        return auth()->user()->hasVerifiedEmail() && $organisation
            && Gate::allows('create', [Invitation::class, $organisation]);
    }

    public function canExportTables(): bool
    {
        return $this->canManage();
    }

    private function authorizeManagement(): void
    {
        abort_unless($this->canManage(), 403);
    }

    public function create(): void
    {
        $this->authorizeManagement();
        $this->closeForm();
        $this->expires_at = now()->addDays(7)->format('Y-m-d\TH:i');
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeManagement();
        $invitation = Invitation::findOrFail($id);
        $this->authorize('update', $invitation);
        $this->closeForm();
        $this->editingId = $id;
        $this->email = $invitation->email;
        $this->expires_at = $invitation->expires_at->format('Y-m-d\TH:i');
        $this->showForm = true;
    }

    public function save(InvitationService $service): void
    {
        $this->authorizeManagement();
        $rules = ['email' => ['required', 'email', 'max:255'], 'expires_at' => ['required', 'date', 'after:now']];
        if ($this->editingId === null) {
            $rules['role_id'] = ['required', 'integer'];
        }
        $data = $this->validate($rules);
        if ($this->editingId !== null) {
            $invitation = Invitation::findOrFail($this->editingId);
            $this->authorize('update', $invitation);
            $invitation->update($data);
        } else {
            [, $token, $emailSent] = $service->create(auth()->user(), $data);
            $this->invitationEmailSent = $emailSent;
            $this->createdToken = $token;
        }
        $this->showForm = false;
        session()->flash($this->editingId === null && ! $this->invitationEmailSent ? 'warning' : 'status',
            $this->editingId === null && ! $this->invitationEmailSent
                ? __('Invitation created, but the email was not sent. Configure SMTP in the server mail settings; you can share the code below in the meantime.')
                : __('Invitation saved successfully.'));
    }

    public function closeForm(): void
    {
        $this->reset(['showForm', 'editingId', 'email', 'role_id', 'expires_at', 'createdToken', 'invitationEmailSent']);
        $this->resetValidation();
    }

    public function revoke(int $id): void
    {
        $this->authorizeManagement();
        $invitation = Invitation::findOrFail($id);
        $this->authorize('delete', $invitation);
        $invitation->forceFill(['revoked_at' => now(), 'revoked_by_user_id' => auth()->id()])->save();
        session()->flash('status', __('Invitation revoked.'));
    }

    public function accept(InvitationService $service): void
    {
        $this->validate(['acceptToken' => ['required', 'string', 'max:255']]);
        try {
            $service->accept(auth()->user(), $this->acceptToken);
        } catch (ModelNotFoundException|HttpExceptionInterface $exception) {
            $this->addError('acceptToken', $exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 409
                ? __('Organisation user limit reached.')
                : __('This invitation is invalid, expired, revoked or belongs to another email address.'));

            return;
        }
        $this->reset('acceptToken');
        session()->flash('status', __('Invitation accepted. Your organisation access has been updated.'));
        $this->redirectRoute('dashboard');
    }

    public function render()
    {
        $canManage = $this->canManage();

        return view('livewire.team.index', [
            'canManage' => $canManage,
            'invitations' => $canManage ? Invitation::where('organisation_id', auth()->user()->organisation_id)->with('role')->latest()->paginate($this->exportPageSize(15)) : null,
            'roles' => $canManage ? Role::whereIn('slug', ['project-manager', 'procurement-officer', 'finance', 'user'])->orderBy('name')->get() : collect(),
        ]);
    }
}
