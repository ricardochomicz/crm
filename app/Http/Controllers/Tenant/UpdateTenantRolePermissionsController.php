<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Tenant\UpdateTenantRolePermissionsAction;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTenantRolePermissionsRequest;
use App\Models\Tenant;
use Illuminate\Http\Response;

class UpdateTenantRolePermissionsController extends Controller
{
    public function __invoke(UpdateTenantRolePermissionsRequest $request, UpdateTenantRolePermissionsAction $update): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');
        $update->handle($request->user(), $tenant, TenantUserRole::from($request->route('role')),
            $request->validated('permissions'), (int) $request->validated('version'));

        return response()->noContent();
    }
}
