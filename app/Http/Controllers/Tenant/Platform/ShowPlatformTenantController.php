<?php

namespace App\Http\Controllers\Tenant\Platform;

use App\Enums\TenantStatus;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\PlatformTenantDetailsResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ShowPlatformTenantController extends Controller
{
    public function __invoke(Tenant $tenant): JsonResponse
    {
        Gate::authorize('access-platform-admin');

        $tenant->load('subscription.plan:id,code,name');
        $tenant->loadCount(['users', 'teams']);

        $owner = $tenant->users()
            ->wherePivot('role', TenantUserRole::Owner->value)
            ->orderBy('users.id')
            ->first();

        $subscription = $tenant->subscription;

        $allowsWrites = $subscription?->allowsOperationalWrites() ?? false;

        return response()->json([
            'data' => new PlatformTenantDetailsResource($tenant, ['owner' => $owner, 'allowsWrites' => $allowsWrites]),
        ]);
    }
}
