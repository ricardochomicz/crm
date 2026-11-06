<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Tenant\FilterTenantRoleAction;
use App\Actions\Tenant\SaveTenantRoleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IndexTenantRoleRequest;
use App\Http\Requests\Tenant\SaveTenantRoleRequest;
use App\Http\Resources\Tenant\TenantRoleResource;
use App\Models\Tenant;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TenantRoleController extends Controller
{
    public function index(IndexTenantRoleRequest $request, string $tenantId, FilterTenantRoleAction $action): AnonymousResourceCollection
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        $roles = $action->execute($tenant, $request->validated());

        return TenantRoleResource::collection($roles);
    }

    public function store(SaveTenantRoleRequest $request, string $tenantId, SaveTenantRoleAction $action): TenantRoleResource
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        return new TenantRoleResource($action->execute($tenant, $request->validated()));
    }

    public function update(SaveTenantRoleRequest $request, string $tenantId, string $roleId, SaveTenantRoleAction $action): TenantRoleResource
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        return new TenantRoleResource($action->execute($tenant, $request->validated(), $roleId));
    }
}
