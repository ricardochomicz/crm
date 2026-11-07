<?php

namespace App\Http\Controllers\Tenant\Management;

use App\Actions\Tenant\SelectTenantAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SelectTenantRequest;
use Illuminate\Http\Response;

class SelectTenantController extends Controller
{
    public function __invoke(
        SelectTenantRequest $request,
        SelectTenantAction $action
    ): Response {
        $tenant = $action->execute(
            $request->user(),
            $request->validated('tenant_id')
        );

        $request->session()->put('active_tenant_id', $tenant->getKey());

        return response()->noContent();
    }
}
