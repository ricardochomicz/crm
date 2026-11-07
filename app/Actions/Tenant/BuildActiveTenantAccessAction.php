<?php

namespace App\Actions\Tenant;

use App\Enums\TenantInvitationStatus;
use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\User;

class BuildActiveTenantAccessAction
{
    public function __construct(private ResolveTenantPermissionsAction $permissions) {}

    /** @return array<string, mixed> */
    public function execute(User $user, Tenant $tenant): array
    {
        $grants = $this->permissions->handle($user, $tenant);
        $subscription = $tenant->subscription()->first();
        $canManageTeam = array_intersect($grants, ['members.view', 'invitations.view']) !== [];
        $capacity = null;

        if ($canManageTeam && $subscription !== null) {
            $users = $tenant->users()->count();
            $reserved = $tenant->invitations()
                ->where('status', TenantInvitationStatus::Pending->value)
                ->where('expires_at', '>', now())
                ->count();

            $capacity = [
                'limits' => ['users' => $subscription->user_limit],
                'usage' => ['users' => $users, 'reserved_invitations' => $reserved],
                'remaining' => ['users' => max(0, $subscription->user_limit - $users - $reserved)],
            ];
        }

        return [
            'permissions' => $grants,
            'tenant_role_id' => $tenant->membership->tenant_role_id,
            'role_name' => $tenant->membership->tenantRole?->name,
            'can_manage_permissions' => $tenant->membership->role === TenantUserRole::Owner,
            'allows_operational_writes' => $subscription?->allowsOperationalWrites() ?? false,
            'assignable_roles' => array_values(array_map(
                fn (TenantUserRole $role): string => $role->value,
                array_filter(
                    TenantUserRole::cases(),
                    fn (TenantUserRole $role): bool => $this->permissions->canAssign($user, $tenant, $role)
                )
            )),
            'capacity' => $capacity,
        ];
    }
}
