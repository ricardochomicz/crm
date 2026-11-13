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

Broadcast::channel('tenant.{tenantId}.member.{membershipId}.notifications', function ($user, string $tenantId, string $membershipId): bool {
    return \App\Models\TenantUser::query()->where('tenant_id', $tenantId)
        ->where('id', $membershipId)->where('user_id', $user->id)
        ->where('status', 'ACTIVE')->exists();
});

Broadcast::channel('tenant.{tenantId}.commercial', function ($user, string $tenantId): bool {
    $tenant = Tenant::query()->find($tenantId);

    return $tenant !== null && (
        $user->can('viewOpportunities', $tenant) ||
        $user->can('viewCalendar', $tenant) ||
        $user->can('viewTeamCalendar', $tenant)
    );
});
