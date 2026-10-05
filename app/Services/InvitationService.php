<?php

namespace App\Services;

use App\Http\Requests\StoreInvitationRequest;
use App\Models\Invitation;
use App\Models\Organisation;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class InvitationService
{
    /** @return array{0: Invitation, 1: string} */
    public function create(User $user, array $data): array
    {
        $organisation = Organisation::findOrFail($user->organisation_id);
        Gate::forUser($user)->authorize('create', [Invitation::class, $organisation]);
        $data = Validator::make($data, (new StoreInvitationRequest)->rules())->validate();
        $token = Str::random(64);
        $invitation = Invitation::create($data + [
            'organisation_id' => $organisation->id, 'inviter_user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
        ]);

        return [$invitation, $token];
    }

    public function accept(User $user, string $token): Invitation
    {
        return DB::transaction(function () use ($token, $user) {
            $user = $user->newQuery()->lockForUpdate()->findOrFail($user->id);
            $invitation = Invitation::where('token_hash', hash('sha256', trim($token)))->lockForUpdate()->firstOrFail();
            abort_if($invitation->accepted_at || $invitation->revoked_at || ! $invitation->expires_at || $invitation->expires_at->isPast(), 410);
            abort_unless(mb_strtolower($user->email) === mb_strtolower($invitation->email), 403);
            $role = Role::findOrFail($invitation->role_id);
            abort_unless(in_array($role->slug, ['project-manager', 'procurement-officer', 'finance', 'user'], true), 422);

            $subscription = Subscription::with('plan')->where('organisation_id', $invitation->organisation_id)
                ->whereIn('status', ['active', 'trial', 'grace_period'])->lockForUpdate()->latest('id')->first();
            $limit = $subscription?->plan?->max_users;
            if ($limit !== null) {
                $members = $invitation->organisation->users()->count();
                $pending = Invitation::where('organisation_id', $invitation->organisation_id)->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->count();
                abort_if($members + $pending > $limit, 409, 'Organisation user limit reached.');
            }

            if ($user->organisation_id !== $invitation->organisation_id) {
                $user->roles()->detach();
                $user->permissions()->detach();
            }
            $user->organisation_id = $invitation->organisation_id;
            $user->save();
            $user->roles()->syncWithoutDetaching([$role->id => ['organisation_id' => $invitation->organisation_id]]);
            $invitation->forceFill(['accepted_at' => now()])->save();

            return $invitation;
        });
    }
}
