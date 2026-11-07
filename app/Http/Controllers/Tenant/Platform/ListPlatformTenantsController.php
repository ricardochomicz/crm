<?php

namespace App\Http\Controllers\Tenant\Platform;

use App\Actions\Tenant\FilterTenantAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ListPlatformTenantsRequest;
use App\Http\Resources\Tenant\TenantResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListPlatformTenantsController extends Controller
{
    public function __invoke(
        ListPlatformTenantsRequest $request,
        FilterTenantAction $action
    ): AnonymousResourceCollection {
        return TenantResource::collection(
            $action->execute($request->validated())
        );
    }
}
