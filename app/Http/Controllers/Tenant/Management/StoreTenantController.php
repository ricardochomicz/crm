<?php

namespace App\Http\Controllers\Tenant\Management;

use App\Actions\Tenant\CreateTenantAction;
use App\Enums\TenantType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreTenantRequest;
use App\Http\Resources\Tenant\CreatedTenantResource;
use Illuminate\Http\JsonResponse;

class StoreTenantController extends Controller
{
    public function __invoke(
        StoreTenantRequest $request,
        CreateTenantAction $action
    ): JsonResponse {
        $tenantData = $request->validated('tenant');
        $tenantData['type'] = TenantType::from($tenantData['type']);

        $tenant = $action->handle(
            $request->user(),
            $request->validated('subscription_plan_id'),
            $tenantData,
            $request->validated('operator_ids')
        );

        $request->session()->put('active_tenant_id', $tenant->getKey());

        return (new CreatedTenantResource($tenant))
            ->response()
            ->setStatusCode(201);
    }
}
