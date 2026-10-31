<?php

namespace App\Http\Controllers\Team;

use App\Actions\Team\UpdateTeamAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Team\UpdateTeamRequest;
use App\Models\Tenant;
use Illuminate\Http\Response;

class UpdateTeamController extends Controller
{
    public function __invoke(
        UpdateTeamRequest $request,
        UpdateTeamAction $updateTeamAction,
    ): Response {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        $updateTeamAction->handle(
            $request->user(),
            $tenant,
            (string) $request->route('teamId'),
            $request->validated('name'),
        );

        return response()->noContent();
    }
}
