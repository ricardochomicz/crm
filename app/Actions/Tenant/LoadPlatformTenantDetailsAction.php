<?php

namespace App\Actions\Tenant;

use App\Enums\TenantUserRole;
use App\Models\Tenant;

class LoadPlatformTenantDetailsAction
{
    /** @return array{owner: \App\Models\User|null, allowsWrites: bool} */
    public function execute(Tenant $tenant): array
    {
        $tenant->load('subscription.plan:id,code,name');
        $tenant->loadCount(['users', 'teams']);

        $owner = $tenant->users()
            ->wherePivot('role', TenantUserRole::Owner->value)
            ->orderBy('users.id')
            ->first();

        return [
            'owner' => $owner,
            'allowsWrites' => $tenant->subscription?->allowsOperationalWrites() ?? false,
        ];
    }
}
