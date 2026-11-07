<?php

namespace App\Http\Controllers\Team;

use App\Actions\Tenant\FilterTenantUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Team\ListTeamMembersRequest;
use App\Http\Resources\Tenant\TenantUserResource;
use App\Models\Tenant;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListTeamMembersController extends Controller
{
    public function __invoke(
        ListTeamMembersRequest $request,
        FilterTenantUserAction $action
    ): AnonymousResourceCollection {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        $team = $tenant->teams()
            ->whereKey((string) $request->route('teamId'))
            ->firstOrFail();

        return TenantUserResource::collection(
            $action->execute($team, $request->validated())
        );
    }
}
