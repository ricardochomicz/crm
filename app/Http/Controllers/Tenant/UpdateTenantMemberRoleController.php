<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Tenant\UpdateTenantMemberRoleAction;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTenantMemberRoleRequest;
use App\Models\Tenant;
use Illuminate\Http\Response;

class UpdateTenantMemberRoleController extends Controller
{
    public function __invoke(
        UpdateTenantMemberRoleRequest $request,
        UpdateTenantMemberRoleAction $updateTenantMemberRoleAction,
    ): Response {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        $updateTenantMemberRoleAction->handle(
            $request->user('web'),
            $tenant,
            (string) $request->route('membershipId'),
            TenantUserRole::from($request->validated('role')),
        );

        return response()->noContent();
    }
}