<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Actions\Tenant\FilterTenantUserAction;
use App\Http\Requests\Team\ListTeamMembersRequest;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Http\Resources\Tenant\TenantUserResource;

class ListTeamMembersController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(ListTeamMembersRequest $request): array
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        $team = $tenant->teams()
            ->whereKey((string) $request->route('teamId'))
            ->firstOrFail();

        $filters = $request->validated();

        return (new FilterTenantUserAction)->execute($team, $filters)
            ->through(fn (TenantUser $membership): array => (new TenantUserResource($membership))->resolve($request))
            ->toArray();
    }
}
