<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptInvitationRequest;
use App\Http\Requests\StoreInvitationRequest;
use App\Http\Requests\UpdateInvitationRequest;
use App\Http\Resources\InvitationResource;
use App\Models\Invitation;
use App\Models\Organisation;
use App\Models\Role;
use App\Services\InvitationService;
use Illuminate\Http\Request;

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
        [$invitation, $token] = app(InvitationService::class)->create($request->user(), $request->validated());

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

    /**
     * Consume a single-use invitation using its raw five-digit code.
     *
     * Brute-force protection is the route's throttle:5,1 middleware combined with the
     * six-hour expiry, single-use consumption and the invitee email match, so no
     * second, conflicting limiter is applied here.
     */
    public function accept(AcceptInvitationRequest $request)
    {
        $result = app(InvitationService::class)->accept($request->user(), $request->validated('token'));

        return new InvitationResource($result);
    }
}
