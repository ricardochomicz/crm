<?php

namespace App\Actions\Tenant;

use App\Enums\TenantStatus;
use App\Enums\TenantUserStatus;
use App\Models\Tenant;
use App\Models\User;

class SelectTenantAction
{
    public function execute(User $user, string $tenantId): Tenant
    {
        return $user->tenants()
            ->where('tenants.status', TenantStatus::Active->value)
            ->wherePivot('status', TenantUserStatus::Active->value)
            ->whereKey($tenantId)
            ->firstOrFail();
    }
}
