<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Actions\Tenant\FilterTenantAction;
use App\Http\Resources\Tenant\TenantResource;
use App\Http\Requests\Tenant\ListPlatformTenantsRequest;
use App\Models\Tenant;

class ListPlatformTenantsController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(ListPlatformTenantsRequest $request): array
    {
        $filters = $request->validated();

        return (new FilterTenantAction)->execute($filters)
            ->through(fn (Tenant $tenant): array => (new TenantResource($tenant))->resolve($request))
            ->toArray();
    }
}
