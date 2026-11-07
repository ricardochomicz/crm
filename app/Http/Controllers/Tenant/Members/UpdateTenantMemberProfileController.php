<?php

namespace App\Http\Controllers\Tenant\Members;

use App\Actions\Tenant\FindTenantMemberProfileAction;
use App\Actions\Tenant\UpdateTenantMemberProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTenantMemberProfileRequest;
use App\Http\Resources\Tenant\TenantMemberProfileResource;
use App\Models\Tenant;

class UpdateTenantMemberProfileController extends Controller
{
    public function __invoke(
        UpdateTenantMemberProfileRequest $request,
        string $tenantId,
        string $membershipId,
        FindTenantMemberProfileAction $finder,
        UpdateTenantMemberProfileAction $action
    ): TenantMemberProfileResource {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        return new TenantMemberProfileResource(
            $action->execute($finder->execute($tenant, $membershipId), $request->validated())
        );
    }
}
