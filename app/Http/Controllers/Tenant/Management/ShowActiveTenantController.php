<?php

namespace App\Http\Controllers\Tenant\Management;

use App\Actions\Tenant\BuildActiveTenantAccessAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\ActiveTenantResource;
use App\Models\Tenant;
use Illuminate\Http\Request;

class ShowActiveTenantController extends Controller
{
    public function __invoke(
        Request $request,
        BuildActiveTenantAccessAction $action
    ): ActiveTenantResource {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        return new ActiveTenantResource(
            $tenant,
            $action->execute($request->user(), $tenant)
        );
    }
}
