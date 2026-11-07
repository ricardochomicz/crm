<?php

namespace App\Http\Controllers\Team;

use App\Actions\Team\FilterTeamAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Team\ListTeamsRequest;
use App\Http\Resources\Team\TeamResource;
use App\Models\Tenant;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListTeamsController extends Controller
{
    public function __invoke(
        ListTeamsRequest $request,
        FilterTeamAction $action
    ): AnonymousResourceCollection {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        return TeamResource::collection(
            $action->execute($tenant, $request->validated())
        );
    }
}
