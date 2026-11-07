<?php

namespace App\Http\Controllers\Tenant\Platform;

use App\Actions\Tenant\LoadPlatformTenantDetailsAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\PlatformTenantDetailsResource;
use App\Models\Tenant;
use Illuminate\Support\Facades\Gate;

class ShowPlatformTenantController extends Controller
{
    public function __invoke(
        Tenant $tenant,
        LoadPlatformTenantDetailsAction $action
    ): PlatformTenantDetailsResource {
        Gate::authorize('access-platform-admin');

        return new PlatformTenantDetailsResource(
            $tenant,
            $action->execute($tenant)
        );
    }
}
