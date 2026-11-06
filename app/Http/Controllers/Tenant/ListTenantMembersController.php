<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\TenantMemberResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ListTenantMembersController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(Request $request, ResolveTenantPermissionsAction $permissions): array
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        Gate::forUser($request->user())
            ->authorize('viewMembers', $tenant);

        $assignableRoles = array_filter(TenantUserRole::cases(),
            fn (TenantUserRole $role): bool => $permissions->canAssign($request->user(), $tenant, $role));

        return $tenant->users()
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->paginate(20, [
                'users.id',
                'users.name',
                'users.email',
            ])
            ->through(fn (User $user): array => (new TenantMemberResource($user, $assignableRoles))->resolve($request))
            ->toArray();
    }
}
