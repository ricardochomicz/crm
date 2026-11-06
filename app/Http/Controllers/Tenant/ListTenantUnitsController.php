<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Tenant\FilterTenantUnitAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexTenantUnitRequest;
use App\Http\Resources\Tenant\TenantUnitResource;
use App\Models\Tenant;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListTenantUnitsController extends Controller
{
    public function __invoke(IndexTenantUnitRequest $request, FilterTenantUnitAction $action): AnonymousResourceCollection
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');
        $canManage = $request->user()->can('manageUnits', $tenant);

        $units = $action->execute(
            $tenant,
            $request->validated(),
            $request->user(),
            $canManage,
        );

        return TenantUnitResource::collection($units)->additional([
            'meta' => [
                'can_manage' => $canManage,
            ],
        ]);
    }
}
