<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Tenant\UpdateTenantMemberStatusAction;
use App\Enums\TenantUserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTenantMemberStatusRequest;
use App\Models\Tenant;
use Illuminate\Http\Response;

class UpdateTenantMemberStatusController extends Controller
{
    public function __invoke(
        UpdateTenantMemberStatusRequest $request,
        UpdateTenantMemberStatusAction $updateTenantMemberStatusAction,
    ): Response {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        $updateTenantMemberStatusAction->handle(
            $request->user(),
            $tenant,
            (string) $request->route('membershipId'),
            TenantUserStatus::from($request->validated('status')),
        );

        return response()->noContent();
    }
}
