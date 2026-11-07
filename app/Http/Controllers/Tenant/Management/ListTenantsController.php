<?php

namespace App\Http\Controllers\Tenant\Management;

use App\Actions\Tenant\FilterUserTenantsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ListTenantsRequest;
use App\Http\Resources\Tenant\UserTenantResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListTenantsController extends Controller
{
    public function __invoke(
        ListTenantsRequest $request,
        FilterUserTenantsAction $action
    ): AnonymousResourceCollection {
        return UserTenantResource::collection(
            $action->execute($request->user(), $request->validated())
        );
    }
}
