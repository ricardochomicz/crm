<?php

namespace App\Http\Controllers\Tenant\Members;

use App\Actions\Tenant\FindTenantMemberProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ShowTenantMemberProfileRequest;
use App\Http\Resources\Tenant\TenantMemberProfileResource;
use App\Models\Tenant;

class ShowTenantMemberProfileController extends Controller
{
    public function __invoke(
        ShowTenantMemberProfileRequest $request,
        string $tenantId,
        string $membershipId,
        FindTenantMemberProfileAction $action
    ): TenantMemberProfileResource {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        return new TenantMemberProfileResource($action->execute($tenant, $membershipId));
    }
}
