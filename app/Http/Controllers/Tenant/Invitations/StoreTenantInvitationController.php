<?php

namespace App\Http\Controllers\Tenant\Invitations;

use App\Actions\Tenant\CreateTenantInvitationAction;
use App\Actions\Tenant\DispatchTenantInvitationNotificationAction;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreTenantInvitationRequest;
use App\Http\Resources\Tenant\CreatedTenantInvitationResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class StoreTenantInvitationController extends Controller
{
    public function __invoke(
        StoreTenantInvitationRequest $request,
        CreateTenantInvitationAction $action,
        DispatchTenantInvitationNotificationAction $notification
    ): JsonResponse {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');
        $data = $request->validated();

        $result = $action->handle(
            $request->user(),
            $tenant,
            $data['email'],
            TenantUserRole::from($data['role']),
            $data['tenant_role_id'] ?? null
        );

        $dispatched = $notification->execute(
            $tenant,
            $result['invitation'],
            $result['token']
        );

        return (new CreatedTenantInvitationResource($result['invitation']))
            ->additional([
                'meta' => ['notification_dispatched' => $dispatched],
            ])
            ->response()
            ->setStatusCode(201);
    }
}
