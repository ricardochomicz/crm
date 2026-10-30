<?php

namespace App\Policies;

use App\Enums\TenantStatus;
use App\Enums\TenantUserRole;
use App\Enums\TenantUserStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TenantPolicy
{
    public function update(User $user, Tenant $tenant): bool
    {
        return $user->tenants()
            ->where('tenants.status', TenantStatus::Active->value)
            ->wherePivot('status', TenantUserStatus::Active->value)
            ->wherePivot('role', TenantUserRole::Owner->value)
            ->exists();
    }
}
