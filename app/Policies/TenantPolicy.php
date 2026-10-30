<?php

namespace App\Policies;

use App\Enums\TenantStatus;
use App\Enums\TenantUserRole;
use App\Enums\TenantUserStatus;
use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function update(User $user, Tenant $tenant): bool
    {
        return $user->tenants()
            ->whereKey($tenant->getKey())
            ->where('tenants.status', TenantStatus::Active->value)
            ->wherePivot('status', TenantUserStatus::Active->value)
            ->wherePivot('role', TenantUserRole::Owner->value)
            ->exists();
    }

    public function addMember(User $user, Tenant $tenant): bool
    {
        return $this->update($user, $tenant);
    }

    public function viewMembers(User $user, Tenant $tenant): bool
    {
        return $this->update($user, $tenant);
    }
}
