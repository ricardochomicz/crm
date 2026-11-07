<?php

namespace App\Http\Controllers\Tenant\Members;

use App\Actions\Tenant\FilterTenantMemberAction;
use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ListTenantMembersRequest;
use App\Http\Resources\Tenant\TenantMemberResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListTenantMembersController extends Controller
{
    public function __invoke(
        ListTenantMembersRequest $request,
        ResolveTenantPermissionsAction $permissions,
        FilterTenantMemberAction $filter
    ): AnonymousResourceCollection {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        $assignableRoles = array_values(array_filter(
            TenantUserRole::cases(),
            fn (TenantUserRole $role): bool => $permissions->canAssign($request->user(), $tenant, $role)
        ));

        $members = $filter->execute($tenant, $request->validated());

        $members->through(
            fn (User $user): TenantMemberResource => new TenantMemberResource($user, $assignableRoles)
        );

        return TenantMemberResource::collection($members);
    }
}
