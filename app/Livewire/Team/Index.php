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
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $email = '';
    public ?int $role_id = null;
    public string $expires_at = '';
    public string $acceptToken = '';
    public bool $showForm = false;
    #[Locked]
    public string $createdToken = '';
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
            [, $token] = $service->create(auth()->user(), $data);
            $this->createdToken = $token;
        }
        $this->showForm = false;
        session()->flash('status', __('Invitation saved successfully.'));
    }

    public function closeForm(): void
    {
        $this->reset(['showForm', 'editingId', 'email', 'role_id', 'expires_at', 'createdToken']);
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
            'invitations' => $canManage ? Invitation::where('organisation_id', auth()->user()->organisation_id)->with('role')->latest()->paginate(15) : null,
            'roles' => $canManage ? Role::whereIn('slug', ['project-manager', 'procurement-officer', 'finance', 'user'])->orderBy('name')->get() : collect(),
        ]);
    }
}
