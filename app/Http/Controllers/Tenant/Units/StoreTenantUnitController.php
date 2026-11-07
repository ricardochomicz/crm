<?php

namespace App\Http\Controllers\Tenant\Units;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\TenantUnitDetailsResource;
use App\Http\Requests\Tenant\SaveTenantUnitRequest;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class StoreTenantUnitController extends Controller
{
    public function __invoke(SaveTenantUnitRequest $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');
        $unit = $tenant->units()->create($request->validated());

        return response()->json(['data' => new TenantUnitDetailsResource($unit)], 201);
    }
}
