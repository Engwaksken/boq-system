<div class="boq-page-stack">
    <x-ui.page-header :title="__('Team Invitations')" icon="fa-user-plus" :subtitle="__('Invite colleagues or accept an invitation to join an organisation.')">
        <x-slot:actions>@if($canManage)<x-ui.button type="button" icon="fa-plus" wire:click="create">{{ __('Invite team member') }}</x-ui.button><x-ui.button variant="secondary" :href="route('team.assignments')" icon="fa-users">{{ __('Project Assignments') }}</x-ui.button>@endif</x-slot:actions>
    </x-ui.page-header>
    <x-ui.flash />
    @if($canManage)
        <x-ui.tabs :label="__('Team workspace tabs')">
            <x-ui.tab :href="route('team.index')" :active="request()->routeIs('team.index')" icon="fa-user-plus">{{ __('Team Invitations') }}</x-ui.tab>
            <x-ui.tab :href="route('team.assignments')" :active="request()->routeIs('team.assignments')" icon="fa-users">{{ __('Project Assignments') }}</x-ui.tab>
        </x-ui.tabs>
    @endif
    <x-ui.card :title="__('Accept invitation')">
        <form wire:submit="accept" class="space-y-3">
            <x-ui.field :label="__('Invitation token')" for="accept-token" error="acceptToken" required><input id="accept-token" wire:model="acceptToken" class="boq-field" maxlength="255" autocomplete="off" required></x-ui.field>
            <p class="text-sm text-slate-500">{{ __('Sign in with the invited email address before accepting.') }}</p>
            <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="accept">{{ __('Accept invitation') }}</x-ui.button>
        </form>
    </x-ui.card>
    @if($createdToken)
        <x-ui.card :title="__('Invitation created')"><p class="mb-3 text-sm">{{ $invitationEmailSent ? __('An invitation email was sent. If they cannot find it, share this code securely. It is shown only once.') : __('The invitation email was not sent. Configure SMTP in the server mail settings, or share this code securely. It is shown only once.') }}</p><code class="block break-all rounded-lg bg-slate-100 p-3 text-sm">{{ $createdToken }}</code><button type="button" wire:click="closeForm" class="boq-btn-secondary mt-3">{{ __('Dismiss token') }}</button></x-ui.card>
    @endif
    @if($canManage)
        <x-ui.card :padded="false"><div class="overflow-x-auto"><x-ui.table>
            <thead><tr><th>{{ __('Email') }}</th><th>{{ __('Role') }}</th><th>{{ __('Expires at') }}</th><th>{{ __('Status') }}</th><th>{{ __('Actions') }}</th></tr></thead>
            <tbody>@forelse($invitations as $invitation)
                @php($pending = !$invitation->accepted_at && !$invitation->revoked_at && $invitation->expires_at->isFuture())
                <tr wire:key="invitation-{{ $invitation->id }}"><td>{{ $invitation->email }}</td><td>{{ $invitation->role?->name ?: '—' }}</td><td>{{ \App\Support\Format::date($invitation->expires_at) }}</td><td>{{ $invitation->accepted_at ? __('Accepted') : ($invitation->revoked_at ? __('Disabled') : ($pending ? __('Pending') : __('Expired'))) }}</td><td>@if($pending)<button type="button" wire:click="edit({{ $invitation->id }})" class="boq-btn-ghost">{{ __('Edit') }}</button><button type="button" wire:click="revoke({{ $invitation->id }})" wire:confirm="{{ __('Disable this invitation? The invited person will no longer be able to join with it.') }}" class="boq-btn-ghost">{{ __('Disable') }}</button>@endif</td></tr>
            @empty<tr><td colspan="5"><x-ui.empty-state icon="fa-user-plus" :title="__('No invitations yet.')" /></td></tr>@endforelse</tbody>
        </x-ui.table></div><div class="p-4">{{ $invitations->links() }}</div></x-ui.card>
    @endif
    @if($showForm)
        <x-ui.modal :title="__('Invite team member')" close="closeForm" submit="save">
            <div class="space-y-4">
                <x-ui.field :label="__('Email')" for="invite-email" error="email" required><input id="invite-email" type="email" wire:model="email" class="boq-field" maxlength="255" required></x-ui.field>
                @if(!$editingId)<x-ui.field :label="__('Role')" for="invite-role" error="role_id" required><select id="invite-role" wire:model="role_id" class="boq-field"><option value="">{{ __('Select role') }}</option>@foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach</select></x-ui.field>@endif
                <x-ui.field :label="__('Expires at')" for="invite-expiry" error="expires_at" required><input id="invite-expiry" type="datetime-local" wire:model="expires_at" class="boq-field" required><p class="mt-1 text-xs text-slate-500">{{ config('app.timezone') }}</p></x-ui.field>
            </div>
            <x-slot:footer><x-ui.button type="button" variant="secondary" wire:click="closeForm">{{ __('Cancel') }}</x-ui.button><x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">{{ __('Save invitation') }}</x-ui.button></x-slot:footer>
        </x-ui.modal>
    @endif
</div>
