<?php

namespace App\Http\Controllers\Tenant\Invitations;

use App\Actions\Tenant\FilterTenantInvitationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ListTenantInvitationsRequest;
use App\Http\Resources\Tenant\TenantInvitationResource;
use App\Models\Tenant;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListTenantInvitationsController extends Controller
{
    public function __invoke(
        ListTenantInvitationsRequest $request,
        FilterTenantInvitationAction $action
    ): AnonymousResourceCollection {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        return TenantInvitationResource::collection(
            $action->execute($tenant, $request->validated())
        );
    }
}
