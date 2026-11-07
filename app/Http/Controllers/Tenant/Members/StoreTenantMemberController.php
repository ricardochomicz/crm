<?php

namespace App\Http\Controllers\Tenant\Members;

use App\Actions\Tenant\CreateTenantMemberAction;
use App\Actions\Tenant\SendTenantMemberPasswordLinkAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreTenantMemberRequest;
use App\Http\Resources\Tenant\CreatedTenantMemberResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class StoreTenantMemberController extends Controller
{
    public function __invoke(
        StoreTenantMemberRequest $request,
        CreateTenantMemberAction $action,
        SendTenantMemberPasswordLinkAction $passwordLink
    ): JsonResponse {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        $user = $action->handle(
            $request->user(),
            $tenant,
            $request->safe()->only(['name', 'email', 'role'])
        );

        $linkSent = $passwordLink->execute($user);

        return (new CreatedTenantMemberResource($user))
            ->additional([
                'meta' => ['password_link_sent' => $linkSent],
            ])
            ->response()
            ->setStatusCode(201);
    }
}
