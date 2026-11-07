<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Tenant\FilterTenantPermissionChangesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ListTenantPermissionChangesRequest;
use App\Http\Resources\Tenant\TenantPermissionChangeResource;
use App\Models\Tenant;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListTenantPermissionChangesController extends Controller
{
    public function __invoke(
        ListTenantPermissionChangesRequest $request,
        FilterTenantPermissionChangesAction $action
    ): AnonymousResourceCollection {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        return TenantPermissionChangeResource::collection(
            $action->execute($tenant->id, $request->validated())
        );
    }
}
