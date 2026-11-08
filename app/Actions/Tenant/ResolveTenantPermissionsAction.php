<?php

namespace App\Actions\Tenant;

use App\Enums\TenantPermission;
use App\Enums\TenantStatus;
use App\Enums\TenantUserRole;
use App\Enums\TenantUserStatus;
use App\Models\Tenant;
use App\Models\TenantRole;
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

        if ($membership === null) {
            return [];
        }
        if ($membership->role === TenantUserRole::Owner) {
            return TenantPermission::keys();
        }
        $customRole = $membership->tenantRole;

        return $customRole !== null ? $customRole->effectivePermissions() : $this->forRole($tenant, $membership->role)['permissions'];
    }

    public function allows(User $user, Tenant $tenant, TenantPermission $permission): bool
    {
        return in_array($permission->value, $this->handle($user, $tenant), true);
    }

    public function defaultRole(Tenant $tenant, TenantUserRole $role): ?TenantRole
    {
        $code = match ($role) {
            TenantUserRole::Owner => 'OWNER',
            TenantUserRole::Manager => 'MANAGER',
            TenantUserRole::Consultant => 'TELEMARKETING',
            TenantUserRole::Assistant => 'BACKOFFICE',
        };

        return $tenant->roles()->where('code', $code)->first();
    }

    public function resolveAssignableRole(User $user, Tenant $tenant, TenantUserRole $role, ?string $roleId = null): TenantRole
    {
        $selectedRole = $roleId === null
            ? $this->defaultRole($tenant, $role)
            : $tenant->roles()->findOrFail($roleId);

        abort_unless($selectedRole !== null && $selectedRole->is_active, 422, 'Selecione um cargo ativo desta empresa.');
        abort_unless($selectedRole->legacy_role === $role->value && $selectedRole->code !== 'OWNER', 422, 'Cargo incompatível com o papel informado.');
        abort_unless($this->canAssign($user, $tenant, $role, $selectedRole), 403, 'Você não pode atribuir um cargo com permissões superiores às suas.');

        return $selectedRole;
    }

    public function canAssign(User $user, Tenant $tenant, TenantUserRole $role, ?TenantRole $selectedRole = null): bool
    {
        $membership = $this->membership($user, $tenant);
        if (! $this->canManageRole($membership, $role)) {
            return false;
        }

        $selectedRole ??= $this->defaultRole($tenant, $role);
        if ($selectedRole !== null && (
            $selectedRole->tenant_id !== $tenant->id || ! $selectedRole->is_active
            || $selectedRole->legacy_role !== $role->value || $selectedRole->code === 'OWNER'
        )) {
            return false;
        }

        $targetPermissions = $selectedRole?->effectivePermissions() ?? $this->forRole($tenant, $role)['permissions'];

        return $membership->role === TenantUserRole::Owner
            || array_diff($targetPermissions, $this->handle($user, $tenant)) === [];
    }

    public function canManageMember(User $user, Tenant $tenant, TenantUser $member): bool
    {
        $membership = $this->membership($user, $tenant);
        if ($member->tenant_id !== $tenant->id || $member->user_id === $user->id
            || ! $this->canManageRole($membership, $member->role)) {
            return false;
        }

        $targetPermissions = $member->tenantRole?->effectivePermissions()
            ?? $this->forRole($tenant, $member->role)['permissions'];

        return $membership->role === TenantUserRole::Owner
            || array_diff($targetPermissions, $this->handle($user, $tenant)) === [];
    }

    private function canManageRole(?TenantUser $membership, TenantUserRole $role): bool
    {
        if ($membership === null || $role === TenantUserRole::Owner) {
            return false;
        }

        return $membership->role === TenantUserRole::Owner || $role !== TenantUserRole::Manager;
    }
}
