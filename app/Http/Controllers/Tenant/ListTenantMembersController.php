<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Controller;
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
            ->through(fn (User $user): array => [
                'id' => $user->membership->id,
                'user_id' => $user->id,
                'can_manage' => $user->id !== $request->user()->id && in_array($user->membership->role, $assignableRoles, true),
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->membership->role->value,
                'tenant_role_id' => $user->membership->tenant_role_id,
                'role_name' => $user->membership->tenantRole?->name,
                'status' => $user->membership->status->value,
                'joined_at' => $user->membership->joined_at?->toIso8601String(),
            ])
            ->toArray();
    }
}
