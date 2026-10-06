<div class="boq-page-stack">
    <x-ui.page-header :title="__('Project Assignments')" icon="fa-users" :subtitle="__('Assign organisation members to projects so they can access BOQs and record expenses.')">
        <x-slot:actions><x-ui.button variant="secondary" :href="route('team.index')" icon="fa-user-plus">{{ __('Team Invitations') }}</x-ui.button></x-slot:actions>
    </x-ui.page-header>
    <x-ui.flash />
    <x-ui.tabs :label="__('Team workspace tabs')">
        <x-ui.tab :href="route('team.index')" :active="request()->routeIs('team.index')" icon="fa-user-plus">{{ __('Team Invitations') }}</x-ui.tab>
        <x-ui.tab :href="route('team.assignments')" :active="request()->routeIs('team.assignments')" icon="fa-users">{{ __('Project Assignments') }}</x-ui.tab>
    </x-ui.tabs>
    <x-ui.card :title="__('Assign project member')">
        <form wire:submit="save" class="grid gap-4 sm:grid-cols-3">
            <x-ui.field :label="__('Project')" for="assignment-project" error="projectId" required><select id="assignment-project" wire:model="projectId" class="boq-field"><option value="">{{ __('Select a project') }}</option>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->name }}</option>@endforeach</select></x-ui.field>
            <x-ui.field :label="__('Team member')" for="assignment-user" error="userId" required><select id="assignment-user" wire:model="userId" class="boq-field"><option value="">{{ __('Select a member') }}</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->name }} ({{ $member->email }})</option>@endforeach</select></x-ui.field>
            <x-ui.field :label="__('Project role')" for="assignment-role" error="role" required><select id="assignment-role" wire:model="role" class="boq-field"><option value="user">{{ __('User') }}</option><option value="project-manager">{{ __('Project Manager') }}</option><option value="procurement-officer">{{ __('Procurement Officer') }}</option><option value="finance">{{ __('Finance') }}</option></select></x-ui.field>
            <p class="text-sm text-slate-500 sm:col-span-3">{{ __('A project assignment grants project access without changing the member\'s organisation role.') }}</p>
            <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">{{ __('Save assignment') }}</x-ui.button>
        </form>
    </x-ui.card>
    <x-ui.card :padded="false"><div class="overflow-x-auto"><x-ui.table>
        <thead><tr><th>{{ __('Project') }}</th><th>{{ __('Team member') }}</th><th>{{ __('Project role') }}</th><th>{{ __('Actions') }}</th></tr></thead>
        <tbody>@forelse($assignments as $assignment)<tr wire:key="assignment-{{ $assignment->id }}"><td>{{ $assignment->project->name }}</td><td>{{ $assignment->user->name }}<div class="text-xs text-slate-500">{{ $assignment->user->email }}</div></td><td>{{ __(\Illuminate\Support\Str::headline($assignment->role)) }}</td><td><button type="button" wire:click="revoke({{ $assignment->id }})" wire:confirm="{{ __('Remove this person from the project? They will no longer have access through this assignment.') }}" class="boq-btn-ghost">{{ __('Remove') }}</button></td></tr>@empty<tr><td colspan="4"><x-ui.empty-state icon="fa-users" :title="__('No project assignments found.')" /></td></tr>@endforelse</tbody>
    </x-ui.table></div><div class="p-4">{{ $assignments->links() }}</div></x-ui.card>
</div>
