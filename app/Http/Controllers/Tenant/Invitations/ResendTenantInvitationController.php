<?php

namespace App\Http\Controllers\Tenant\Invitations;

use App\Actions\Tenant\DispatchTenantInvitationNotificationAction;
use App\Actions\Tenant\ResendTenantInvitationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ResendTenantInvitationRequest;
use App\Http\Resources\Tenant\ResentTenantInvitationResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class ResendTenantInvitationController extends Controller
{
    public function __invoke(
        ResendTenantInvitationRequest $request,
        ResendTenantInvitationAction $action,
        DispatchTenantInvitationNotificationAction $notification
    ): JsonResponse {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        $result = $action->handle(
            $request->user(),
            $tenant,
            (string) $request->route('invitationId')
        );

        $dispatched = $notification->execute(
            $tenant,
            $result['invitation'],
            $result['token']
        );

        return (new ResentTenantInvitationResource($result['invitation']))
            ->additional([
                'meta' => ['notification_dispatched' => $dispatched],
            ])
            ->response();
    }
}
