<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SaveTenantUnitRequest;
use App\Models\Tenant;
use Illuminate\Http\Response;

class UpdateTenantUnitController extends Controller
{
    public function __invoke(SaveTenantUnitRequest $request, string $tenantId, string $unitId): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');
        $tenant->units()->findOrFail($unitId)->update($request->validated());

        return response()->noContent();
    }
}
