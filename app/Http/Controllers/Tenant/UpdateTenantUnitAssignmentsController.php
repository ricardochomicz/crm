<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Tenant\ChangeTenantUnitAssignmentsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTenantUnitAssignmentsRequest;
use App\Models\Tenant;
use Illuminate\Http\Response;

class UpdateTenantUnitAssignmentsController extends Controller
{
    public function __invoke(UpdateTenantUnitAssignmentsRequest $request, string $tenantId, string $unitId, ChangeTenantUnitAssignmentsAction $change): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');
        $change->handle($request->user(), $tenant, $unitId, $request->validated());

        return response()->noContent();
    }
}
