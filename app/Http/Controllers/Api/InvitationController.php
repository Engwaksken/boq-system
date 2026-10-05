<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvitationRequest;
use App\Http\Requests\UpdateInvitationRequest;
use App\Http\Resources\InvitationResource;
use App\Models\Invitation;
use App\Models\Organisation;
use App\Models\Role;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    public function roles(Request $request)
    {
        $organisation = Organisation::findOrFail($request->user()->organisation_id);
        $this->authorize('create', [Invitation::class, $organisation]);

        return response()->json(['data' => Role::whereIn('slug', [
            'project-manager', 'procurement-officer', 'finance', 'user',
        ])->orderBy('name')->get(['id', 'name', 'slug'])]);
    }

    public function index(Request $request)
    {
        $organisation = Organisation::findOrFail($request->user()->organisation_id);
        $this->authorize('create', [Invitation::class, $organisation]);
        return InvitationResource::collection(Invitation::where('organisation_id', $organisation->id)->latest()->paginate(25));
    }

    public function store(StoreInvitationRequest $request)
    {
        $organisation = Organisation::findOrFail($request->user()->organisation_id);
        $this->authorize('create', [Invitation::class, $organisation]);
        $token = Str::random(64);
        $invitation = Invitation::create($request->validated() + [
            'organisation_id' => $organisation->id, 'inviter_user_id' => $request->user()->id,
            'token_hash' => hash('sha256', $token),
        ]);
        return (new InvitationResource($invitation))->additional(['token' => $token])->response()->setStatusCode(201);
    }

    public function show(Invitation $invitation)
    {
        $this->authorize('view', $invitation);
        return new InvitationResource($invitation);
    }

    public function update(UpdateInvitationRequest $request, Invitation $invitation)
    {
        $this->authorize('update', $invitation);
        $invitation->update($request->validated());
        return new InvitationResource($invitation->refresh());
    }

    public function destroy(Invitation $invitation)
    {
        $this->authorize('delete', $invitation);
        $invitation->forceFill(['revoked_at' => now(), 'revoked_by_user_id' => request()->user()->id])->save();
        return new InvitationResource($invitation->refresh());
    }

    /** Consume a single-use invitation using its raw token. */
    public function accept(Request $request)
    {
        $request->validate(['token' => ['required', 'string']]);
        $user = $request->user();
        abort_unless($user, 401);

        $result = DB::transaction(function () use ($request, $user) {
            $user = $user->newQuery()->lockForUpdate()->findOrFail($user->id);
            $invitation = Invitation::where('token_hash', hash('sha256', $request->string('token')->toString()))->lockForUpdate()->firstOrFail();
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
                // Joining another tenant must not carry the old tenant's
                // roles or direct grants into the destination organisation.
                $user->roles()->detach();
                $user->permissions()->detach();
            }
            $user->organisation_id = $invitation->organisation_id;
            $user->save();
            $user->roles()->syncWithoutDetaching([$role->id => ['organisation_id' => $invitation->organisation_id]]);
            $invitation->forceFill(['accepted_at' => now()])->save();
            return $invitation;
        });

        return new InvitationResource($result);
    }
}
