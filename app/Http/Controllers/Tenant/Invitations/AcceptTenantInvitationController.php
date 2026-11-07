<?php

namespace App\Http\Controllers\Tenant\Invitations;

use App\Actions\Tenant\AcceptTenantInvitationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AcceptTenantInvitationRequest;
use App\Http\Resources\Tenant\AcceptedTenantInvitationResource;

class AcceptTenantInvitationController extends Controller
{
    public function __invoke(
        AcceptTenantInvitationRequest $request,
        AcceptTenantInvitationAction $action
    ): AcceptedTenantInvitationResource {
        $membership = $action->handle(
            $request->user(),
            $request->validated('token')
        );

        return new AcceptedTenantInvitationResource($membership);
    }
}
