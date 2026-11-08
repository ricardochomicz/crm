<?php

use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Models\Tenant;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('tenant.{tenantId}.orders', function ($user, string $tenantId): bool {
    $tenant = Tenant::query()->find($tenantId);
    return $tenant !== null && (
        $user->can('viewOpportunities', $tenant) ||
        $user->can('viewCommissions', $tenant)
    );
});
