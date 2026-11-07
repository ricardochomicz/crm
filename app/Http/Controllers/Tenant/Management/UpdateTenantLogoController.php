<?php

namespace App\Http\Controllers\Tenant\Management;

use App\Actions\Tenant\UpdateTenantLogoAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTenantLogoRequest;
use App\Http\Resources\Tenant\TenantLogoResource;
use App\Models\Tenant;

class UpdateTenantLogoController extends Controller
{
    public function __invoke(
        UpdateTenantLogoRequest $request,
        UpdateTenantLogoAction $action
    ): TenantLogoResource {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        $tenant = $action->execute($tenant, $request->file('logo'));

        return new TenantLogoResource($tenant);
    }
}
