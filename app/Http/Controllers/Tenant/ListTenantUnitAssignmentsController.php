<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ListTenantUnitAssignmentsRequest;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ListTenantUnitAssignmentsController extends Controller
{
    /** @return array<string, mixed> */
    public function __invoke(ListTenantUnitAssignmentsRequest $request, string $tenantId, string $unitId): array
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');
        $unit = $tenant->units()->findOrFail($unitId);
        $filters = $request->validated();
        $linked = $filters['mode'] === 'linked';
        $search = trim($filters['q'] ?? '');
        if ($filters['kind'] === 'members') {
            $query = TenantUser::query()->where('tenant_id', $tenant->id)->with('user')
                ->withExists([
                    'team as belongs_to_unit_team' => fn (Builder $teams): Builder => $teams->where('tenant_unit_id', $unitId),
                    'supervisedTeams as supervises_unit_team' => fn (Builder $teams): Builder => $teams->where('tenant_unit_id', $unitId),
                ]);
            $query->whereIn('id', DB::table('tenant_unit_memberships')->select('membership_id')->where('tenant_unit_id', $unitId), not: ! $linked);
            if ($search !== '') {
                $query->whereHas('user', fn (Builder $users): Builder => $users->where(fn (Builder $names): Builder => $names->whereLike('name', '%'.$search.'%')->orWhereLike('email', '%'.$search.'%')));
            }

            return $query->orderBy('id')->paginate(20)->through(fn (TenantUser $member): array => [
                'id' => $member->id, 'name' => $member->user->name, 'email' => $member->user->email,
                'status' => $member->status->value,
                'removal_blocked_reason' => ($member->belongs_to_unit_team || $member->supervises_unit_team)
                    ? 'Este membro integra ou supervisiona uma equipe desta unidade. Atualize a equipe antes de remover o vínculo.' : null,
            ])->toArray();
        }

        $query = $tenant->teams()->with('unit:id,name');
        if ($linked) {
            $query->where('tenant_unit_id', $unitId);
        } else {
            $query->where(fn (Builder $teams): Builder => $teams->where('tenant_unit_id', '!=', $unitId)->orWhereNull('tenant_unit_id'));
        }
        if ($search !== '') {
            $query->whereLike('name', '%'.$search.'%');
        }

        return $query->orderBy('name')->orderBy('id')->paginate(20)->through(fn (Team $team): array => [
            'id' => $team->id, 'name' => $team->name,
            'unit_name' => $team->unit?->name,
            'removal_blocked_reason' => $unit->is_headquarters && $linked ? 'Para retirar uma equipe da matriz, transfira-a para uma filial.' : null,
        ])->toArray();
    }
}
