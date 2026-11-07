<?php

namespace App\Http\Controllers\Tenant\Audit;

use App\Actions\Tenant\FilterTenantStatusChangesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ListTenantStatusChangesRequest;
use App\Http\Resources\Tenant\TenantStatusChangeResource;
use App\Models\Tenant;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListTenantStatusChangesController extends Controller
{
    public function __invoke(
        ListTenantStatusChangesRequest $request,
        Tenant $tenant,
        FilterTenantStatusChangesAction $action
    ): AnonymousResourceCollection {
        return TenantStatusChangeResource::collection(
            $action->execute($tenant, $request->validated())
        );
    }
}
