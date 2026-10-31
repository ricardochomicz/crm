<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Tenant\ChangeTenantStatusAction;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdatePlatformTenantStatusRequest;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UpdatePlatformTenantStatusController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        UpdatePlatformTenantStatusRequest $request,
        Tenant $tenant,
        ChangeTenantStatusAction $changeTenantStatusAction
    ): Response {
        $changeTenantStatusAction->handle(
            $request->user(),
            $tenant,
            TenantStatus::from($request->validated('status')),
            $request->validated('reason')
        );

        return response()->noContent();
    }
}
