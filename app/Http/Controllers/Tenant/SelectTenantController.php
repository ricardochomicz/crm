<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\TenantStatus;
use App\Enums\TenantUserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SelectTenantRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SelectTenantController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(SelectTenantRequest $request): Response
    {
        $tenant = $request->user()
            ->tenants()
            ->where('tenants.status', TenantStatus::Active->value)
            ->wherePivot('status', TenantUserStatus::Active->value)
            ->whereKey($request->validated('tenant_id'))
            ->firstOrFail();

        $request->session()->put('active_tenant_id', $tenant->getKey());

        return response()->noContent();
    }
}
