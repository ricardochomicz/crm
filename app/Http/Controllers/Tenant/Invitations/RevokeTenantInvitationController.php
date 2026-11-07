<?php

namespace App\Http\Controllers\Tenant\Invitations;

use App\Actions\Tenant\RevokeTenantInvitationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\RevokeTenantInvitationRequest;
use App\Models\Tenant;
use Illuminate\Http\Response;

class RevokeTenantInvitationController extends Controller
{
    public function __invoke(
        RevokeTenantInvitationRequest $request,
        RevokeTenantInvitationAction $action,
    ): Response {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        $action->handle(
            $request->user(),
            $tenant,
            (string) $request->route('invitationId'),
        );

        return response()->noContent();
    }
}
