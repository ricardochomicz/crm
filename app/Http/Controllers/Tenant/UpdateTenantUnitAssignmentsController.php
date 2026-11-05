<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTenantUnitAssignmentsRequest;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class UpdateTenantUnitAssignmentsController extends Controller
{
    public function __invoke(UpdateTenantUnitAssignmentsRequest $request, string $tenantId, string $unitId): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');
        $data = $request->validated();
        DB::transaction(function () use ($tenant, $unitId, $data): void {
            Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $unit = $tenant->units()->findOrFail($unitId);
            $headquartersId = $tenant->units()->where('is_headquarters', true)->value('id');
            $previousTeams = $tenant->teams()->where('tenant_unit_id', $unitId)->whereNotIn('id', $data['team_ids'])->get();
            $tenant->teams()->whereIn('id', $previousTeams->modelKeys())->update(['tenant_unit_id' => $headquartersId]);
            $teams = $tenant->teams()->whereIn('id', $data['team_ids'])->get();
            $tenant->teams()->whereIn('id', $data['team_ids'])->update(['tenant_unit_id' => $unitId]);
            $teams = $tenant->teams()->where('tenant_unit_id', $unitId)->get();
            $teamMembers = TenantUser::query()->where('tenant_id', $tenant->id)->whereIn('team_id', $teams->modelKeys())->pluck('id');
            $membershipIds = collect($data['membership_ids'])->merge($teamMembers)->merge($teams->pluck('supervisor_membership_id')->filter())->unique();
            $unit->members()->sync($membershipIds->mapWithKeys(fn (string $id): array => [$id => ['tenant_id' => $tenant->id]])->all());
            if ($headquartersId !== null && $headquartersId !== $unitId) {
                $returnedMembers = TenantUser::query()->where('tenant_id', $tenant->id)->whereIn('team_id', $previousTeams->modelKeys())->pluck('id')
                    ->merge($previousTeams->pluck('supervisor_membership_id')->filter())->unique();
                $tenant->units()->findOrFail($headquartersId)->members()->syncWithoutDetaching($returnedMembers->mapWithKeys(fn (string $id): array => [$id => ['tenant_id' => $tenant->id]])->all());
            }
        }, attempts: 5);

        return response()->noContent();
    }
}
