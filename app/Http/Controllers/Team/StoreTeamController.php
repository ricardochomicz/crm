<?php

namespace App\Http\Controllers\Team;

use App\Actions\Team\CreateTeamAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Team\StoreTeamRequest;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class StoreTeamController extends Controller
{
    public function __invoke(
        StoreTeamRequest $request,
        CreateTeamAction $createTeamAction,
    ): JsonResponse {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        $team = $createTeamAction->handle(
            $request->user('web'),
            $tenant,
            $request->safe()->only([
                'name',
                'supervisor_membership_id',
            ]),
        );

        return response()->json([
            'data' => [
                'id' => $team->id,
                'name' => $team->name,
                'supervisor_membership_id' => $team->supervisor_membership_id
            ],
        ], 201);
    }
}
