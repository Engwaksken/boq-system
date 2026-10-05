<?php

namespace App\Policies;

use App\Models\Invitation;
use App\Models\Organisation;
use App\Models\User;

class InvitationPolicy
{
    private const INVITABLE_ROLES = ['project-manager', 'procurement-officer', 'finance', 'user'];

    public function view(User $user, Invitation $invitation): bool
    {
        return $this->canManage($user, $invitation->organisation_id);
    }

    public function create(User $user, Organisation $organisation): bool
    {
        return $this->canManage($user, $organisation->id);
    }

    /** Only the explicitly supported project roles may be invited. */
    public function inviteRole(User $user, Organisation $organisation, string $roleSlug): bool
    {
        return in_array($roleSlug, self::INVITABLE_ROLES, true)
            && $this->canManage($user, $organisation->id);
    }

    public function assignRole(User $user, Organisation $organisation, string $roleSlug): bool
    {
        return $this->inviteRole($user, $organisation, $roleSlug);
    }

    public function update(User $user, Invitation $invitation): bool
    {
        return $this->canManage($user, $invitation->organisation_id);
    }

    public function delete(User $user, Invitation $invitation): bool
    {
        return $this->update($user, $invitation);
    }

    private function canManage(User $user, ?int $organisationId): bool
    {
        return $organisationId !== null
            && $user->organisation_id === $organisationId
            && $user->roles()
                ->whereIn('roles.slug', ['administrator', 'super-admin', 'super_admin'])
                ->wherePivot('organisation_id', $organisationId)
                ->exists();
    }
}
