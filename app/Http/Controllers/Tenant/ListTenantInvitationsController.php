<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Resources\Tenant\TenantInvitationResource;
use App\Http\Controllers\Controller;
use App\Actions\Tenant\FilterTenantInvitationAction;
use App\Http\Requests\Tenant\ListTenantInvitationsRequest;
use App\Models\Tenant;
use App\Models\TenantInvitation;

class ListTenantInvitationsController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(
        ListTenantInvitationsRequest $request,
    ): array {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        $filters = $request->validated();

        return (new FilterTenantInvitationAction)->execute($tenant, $filters)
            ->through(fn (TenantInvitation $invitation): array => (new TenantInvitationResource($invitation))->resolve($request))
            ->toArray();
    }
}
