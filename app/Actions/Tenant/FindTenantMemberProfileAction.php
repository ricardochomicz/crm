<?php

namespace App\Actions\Tenant;

use App\Models\Tenant;
use App\Models\TenantUser;

class FindTenantMemberProfileAction
{
    public function execute(Tenant $tenant, string $membershipId): TenantUser
    {
        return TenantUser::query()
            ->where('tenant_id', $tenant->getKey())
            ->with('user:id,name,email')
            ->findOrFail($membershipId);
    }
}
