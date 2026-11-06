<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Actions\Team\FilterTeamAction;
use App\Http\Resources\Team\TeamResource;
use App\Http\Requests\Team\ListTeamsRequest;
use App\Models\Team;
use App\Models\Tenant;

class ListTeamsController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(ListTeamsRequest $request): array
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        $filters = $request->validated();

        return (new FilterTeamAction)->execute($tenant, $filters)
            ->through(fn (Team $team): array => (new TeamResource($team))->resolve($request))
            ->toArray();
    }
}