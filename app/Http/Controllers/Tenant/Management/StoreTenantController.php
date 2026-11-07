<?php

namespace App\Http\Controllers\Tenant\Management;

use App\Actions\Tenant\CreateTenantAction;
use App\Enums\TenantType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\CreatedTenantResource;
use App\Http\Requests\Tenant\StoreTenantRequest;
use Illuminate\Http\JsonResponse;

class StoreTenantController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        StoreTenantRequest $request,
        CreateTenantAction $createTenantAction
    ): JsonResponse {
        $tenantData = $request->validated('tenant');
        $tenantData['type'] = TenantType::from($tenantData['type']);

        $tenant = $createTenantAction->handle(
            $request->user(),
            $request->validated('subscription_plan_id'),
            $tenantData,
            $request->validated('operator_ids'),
        );

        $request->session()->put('active_tenant_id', $tenant->getKey());

        return response()->json([
            'data' => new CreatedTenantResource($tenant),
        ], 201);
    }
}
