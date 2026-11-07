<?php

namespace App\Http\Controllers\Tenant\Roles;

use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\TenantPermission;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShowTenantRolePermissionsController extends Controller
{
    /** @return array<string, mixed> */
    public function __invoke(Request $request, ResolveTenantPermissionsAction $permissions): array
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');
        Gate::forUser($request->user())->authorize('managePermissions', $tenant);

        return ['data' => [
            'catalog' => TenantPermission::catalog(),
            'roles' => array_map(fn (TenantUserRole $role): array => [
                'role' => $role->value,
                'protected' => $role === TenantUserRole::Owner,
                ...$permissions->forRole($tenant, $role),
            ], TenantUserRole::cases()),
            'can_edit' => $tenant->subscription()->first()?->allowsOperationalWrites() ?? false,
        ]];
    }
}
