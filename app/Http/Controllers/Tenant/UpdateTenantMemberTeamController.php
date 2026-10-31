<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Tenant\UpdateTenantMemberTeamAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTenantMemberTeamRequest;
use App\Models\Tenant;
use Illuminate\Http\Response;

class UpdateTenantMemberTeamController extends Controller
{
    public function __invoke(
        UpdateTenantMemberTeamRequest $request,
        UpdateTenantMemberTeamAction $updateTenantMemberTeamAction,
    ): Response {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        $updateTenantMemberTeamAction->handle(
            $request->user(),
            $tenant,
            (string) $request->route('membershipId'),
            $request->validated('team_id'),
            $request->validated('reason'),
        );

        return response()->noContent();
    }
}
