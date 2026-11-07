<?php

namespace App\Http\Controllers\Tenant\Roles;

use App\Actions\Tenant\ShowTenantRolePermissionsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ShowTenantRolePermissionsRequest;
use App\Http\Resources\Tenant\TenantRolePermissionsResource;
use App\Models\Tenant;

class ShowTenantRolePermissionsController extends Controller
{
    public function __invoke(
        ShowTenantRolePermissionsRequest $request,
        ShowTenantRolePermissionsAction $action
    ): TenantRolePermissionsResource {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        return new TenantRolePermissionsResource(
            $action->execute($tenant)
        );
    }
}
