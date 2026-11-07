<?php

namespace App\Http\Controllers\Tenant\Units;

use App\Actions\Tenant\FilterTenantUnitAssignmentsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ListTenantUnitAssignmentsRequest;
use App\Http\Resources\Tenant\TenantUnitMemberAssignmentResource;
use App\Http\Resources\Tenant\TenantUnitTeamAssignmentResource;
use App\Models\Team;
use App\Models\Tenant;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListTenantUnitAssignmentsController extends Controller
{
    public function __invoke(
        ListTenantUnitAssignmentsRequest $request,
        string $tenantId,
        string $unitId,
        FilterTenantUnitAssignmentsAction $action
    ): AnonymousResourceCollection {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');
        $unit = $tenant->units()->findOrFail($unitId);
        $filters = $request->validated();

        $assignments = $action->execute($tenant, $unitId, $filters);

        if ($filters['kind'] === 'members') {
            return TenantUnitMemberAssignmentResource::collection($assignments);
        }

        $assignments->through(function (Team $team) use ($unit, $filters): Team {
            $team->unitAssignmentRemovalBlockedReason = $unit->is_headquarters && $filters['mode'] === 'linked'
                ? 'Para retirar uma equipe da matriz, transfira-a para uma filial.'
                : null;

            return $team;
        });

        return TenantUnitTeamAssignmentResource::collection($assignments);
    }
}
