<?php

namespace App\Actions\Tenant;

use App\Enums\TenantPermission;
use App\Enums\TenantUserRole;
use App\Models\Tenant;

class ShowTenantRolePermissionsAction
{
    public function __construct(private ResolveTenantPermissionsAction $permissions) {}

    /** @return array<string, mixed> */
    public function execute(Tenant $tenant): array
    {
        return [
            'catalog' => TenantPermission::catalog(),
            'roles' => array_map(
                fn (TenantUserRole $role): array => [
                    'role' => $role->value,
                    'protected' => $role === TenantUserRole::Owner,
                    ...$this->permissions->forRole($tenant, $role),
                ],
                TenantUserRole::cases()
            ),
            'can_edit' => $tenant->subscription()->first()?->allowsOperationalWrites() ?? false,
        ];
    }
}
