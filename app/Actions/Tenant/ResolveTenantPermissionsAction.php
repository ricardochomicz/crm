<?php

namespace App\Actions\Tenant;

use App\Enums\TenantPermission;
use App\Enums\TenantStatus;
use App\Enums\TenantUserRole;
use App\Enums\TenantUserStatus;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ResolveTenantPermissionsAction
{
    public function membership(User $user, Tenant $tenant): ?TenantUser
    {
        if ($tenant->status !== TenantStatus::Active) {
            return null;
        }

        return TenantUser::query()->where('tenant_id', $tenant->getKey())
            ->where('user_id', $user->getKey())
            ->where('status', TenantUserStatus::Active->value)->first();
    }

    /** @return array{permissions: list<string>, version: int} */
    public function forRole(Tenant $tenant, TenantUserRole $role): array
    {
        if ($role === TenantUserRole::Owner) {
            return ['permissions' => TenantPermission::keys(), 'version' => 0];
        }

        $setting = DB::table('tenant_role_permissions')->where('tenant_id', $tenant->getKey())
            ->where('role', $role->value)->first();

        return [
            'permissions' => $setting === null ? [] : array_values(array_intersect(
                json_decode($setting->permissions, true, flags: JSON_THROW_ON_ERROR),
                TenantPermission::keys(),
            )),
            'version' => (int) ($setting?->version ?? 0),
        ];
    }

    /** @return list<string> */
    public function handle(User $user, Tenant $tenant): array
    {
        $membership = $this->membership($user, $tenant);

        return $membership === null ? [] : $this->forRole($tenant, $membership->role)['permissions'];
    }

    public function allows(User $user, Tenant $tenant, TenantPermission $permission): bool
    {
        return in_array($permission->value, $this->handle($user, $tenant), true);
    }

    public function canAssign(User $user, Tenant $tenant, TenantUserRole $role): bool
    {
        $membership = $this->membership($user, $tenant);
        if ($membership === null || $role === TenantUserRole::Owner) {
            return false;
        }
        if ($membership->role === TenantUserRole::Owner) {
            return true;
        }

        // Managers can assign operational roles, but cannot grant Manager or Owner.
        // The invitation/member-update policies still control who may perform the action.
        if ($membership->role === TenantUserRole::Manager) {
            return in_array($role, [TenantUserRole::Consultant, TenantUserRole::Assistant], true);
        }

        return array_diff($this->forRole($tenant, $role)['permissions'],
            $this->forRole($tenant, $membership->role)['permissions']) === [];
    }

    public function canManageMember(User $user, Tenant $tenant, TenantUser $member): bool
    {
        return $member->tenant_id === $tenant->getKey()
            && $member->user_id !== $user->getKey()
            && $this->canAssign($user, $tenant, $member->role);
    }
}
