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

class InvitationService
{
    /** Invitation codes stay valid for a fixed six hours and cannot be extended. */
    public const VALIDITY_HOURS = 6;

    /** @return array{0: Invitation, 1: string} */
    public function create(User $user, array $data): array
    {
        $organisation = Organisation::findOrFail($user->organisation_id);
        Gate::forUser($user)->authorize('create', [Invitation::class, $organisation]);
        $data = Validator::make($data, (new StoreInvitationRequest)->rules())->validate();

        // The server, not the caller, decides the lifetime so an invitation cannot
        // outlive the brute-force protections that depend on the short window.
        unset($data['expires_at']);

        $code = $this->generateCode();
        $invitation = Invitation::create($data + [
            'organisation_id' => $organisation->id, 'inviter_user_id' => $user->id,
            'token_hash' => Invitation::hashCode($code),
            'expires_at' => now()->addHours(self::VALIDITY_HOURS),
        ]);

        return [$invitation, $code];
    }

    /**
     * Pick a five-digit code that is not already in use by an active invitation.
     *
     * The code space is small (100 000), so a collision is checked against invitations
     * that are still pending and within their validity window; consumed or expired
     * codes may be reissued.
     */
    private function generateCode(): string
    {
        for ($attempt = 0; $attempt < 25; $attempt++) {
            $code = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
            $active = Invitation::where('token_hash', Invitation::hashCode($code))
                ->whereNull('accepted_at')
                ->whereNull('consumed_at')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->exists();

            if (! $active) {
                return $code;
            }
        }

        throw new \RuntimeException('Could not generate a unique invitation code.');
    }

    public function accept(User $user, string $token): Invitation
    {
        $hash = Invitation::hashCode($token);

        return DB::transaction(function () use ($hash, $user) {
            $user = $user->newQuery()->lockForUpdate()->findOrFail($user->id);
            $invitation = Invitation::where('token_hash', $hash)->lockForUpdate()->firstOrFail();

            $invitation->forceFill([
                'attempt_count' => $invitation->attempt_count + 1,
                'last_attempt_at' => now(),
            ])->save();

            abort_if($invitation->accepted_at || $invitation->consumed_at || $invitation->revoked_at || ! $invitation->expires_at || $invitation->expires_at->isPast(), 410);
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
            $invitation->forceFill(['accepted_at' => now(), 'consumed_at' => now()])->save();

            return $invitation;
        });
    }
}
