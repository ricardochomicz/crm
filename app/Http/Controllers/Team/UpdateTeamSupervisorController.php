<?php

namespace App\Http\Controllers\Team;

use App\Actions\Team\UpdateTeamSupervisorAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Team\UpdateTeamSupervisorRequest;
use App\Models\Tenant;
use Illuminate\Http\Response;

class UpdateTeamSupervisorController extends Controller
{
    public function __invoke(
        UpdateTeamSupervisorRequest $request,
        UpdateTeamSupervisorAction $updateTeamSupervisorAction,
    ): Response {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        $updateTeamSupervisorAction->handle(
            $request->user(),
            $tenant,
            (string) $request->route('teamId'),
            $request->validated('supervisor_membership_id'),
        );

        return response()->noContent();
    }
}