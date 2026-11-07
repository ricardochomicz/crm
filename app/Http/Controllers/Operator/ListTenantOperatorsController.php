<?php

namespace App\Http\Controllers\Operator;

use App\Actions\Operator\FilterTenantOperatorsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\ListTenantOperatorsRequest;
use App\Http\Resources\Operator\TenantOperatorResource;
use App\Models\Tenant;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListTenantOperatorsController extends Controller
{
    public function __invoke(ListTenantOperatorsRequest $request, FilterTenantOperatorsAction $action): AnonymousResourceCollection
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');
        $operators = $action->execute($tenant, $request->validated());

        return TenantOperatorResource::collection($operators)->additional([
            'meta' => ['can_manage' => $request->user()->can('manageTenantOperators', $tenant)],
        ]);
    }
}
