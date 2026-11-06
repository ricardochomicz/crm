<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Tenant\FilterTenantMemberAction;
use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ListTenantMembersRequest;
use App\Http\Resources\Tenant\TenantMemberResource;
use App\Models\Tenant;
use App\Models\User;

class ListTenantMembersController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(
        ListTenantMembersRequest $request,
        ResolveTenantPermissionsAction $permissions,
        FilterTenantMemberAction $filter,
    ): array {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        $assignableRoles = array_filter(
            TenantUserRole::cases(),
            fn (TenantUserRole $role): bool => $permissions->canAssign($request->user(), $tenant, $role)
        );

        return $filter->execute($tenant, $request->validated())
            ->through(fn (User $user): array => (new TenantMemberResource($user, $assignableRoles))->resolve($request))
            ->toArray();
    }
}
