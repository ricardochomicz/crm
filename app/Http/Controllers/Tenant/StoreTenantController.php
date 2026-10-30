<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Tenant\CreateTenantAction;
use App\Enums\TenantType;
use App\Http\Controllers\Controller;
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
        $tenantData = $request->validate('tenant');
        $tenantData['type'] = TenantType::from($tenantData['type']);

        $tenant = $createTenantAction->handle(
            $request->user('web'),
            $request->validated('subscription_plan_id'),
            $tenantData,
        );

        $request->session()->put('active_tenant_id', $tenant->getKey());

        return response()->json([
            'data' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'type' => $tenant->type->value,
            ],
        ], 201);
    }
}
