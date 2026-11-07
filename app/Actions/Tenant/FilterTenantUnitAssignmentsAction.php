<?php

namespace App\Actions\Tenant;

use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class FilterTenantUnitAssignmentsAction
{
    /** @param array<string, mixed> $filters */
    public function execute(Tenant $tenant, string $unitId, array $filters): LengthAwarePaginator
    {
        $linked = $filters['mode'] === 'linked';
        $search = trim($filters['q'] ?? '');

        if ($filters['kind'] === 'members') {
            $query = TenantUser::query()
                ->where('tenant_id', $tenant->id)
                ->with(['user', 'team:id,name,tenant_unit_id', 'supervisedTeams:id,name,tenant_unit_id'])
                ->withExists([
                    'team as belongs_to_unit_team' => fn (Builder $teams): Builder => $teams->where('tenant_unit_id', $unitId),
                    'supervisedTeams as supervises_unit_team' => fn (Builder $teams): Builder => $teams->where('tenant_unit_id', $unitId),
                ])
                ->whereIn(
                    'id',
                    DB::table('tenant_unit_memberships')->select('membership_id')->where('tenant_unit_id', $unitId),
                    not: ! $linked
                );

            if ($search !== '') {
                $query->whereHas('user', fn (Builder $users): Builder => $users->where(
                    fn (Builder $names): Builder => $names->whereLike('name', '%'.$search.'%')
                        ->orWhereLike('email', '%'.$search.'%')
                ));
            }

            return $query->orderBy('id')->paginate(20)->appends($filters);
        }

        $query = $tenant->teams()->with('unit:id,name')->withCount('members');

        if ($linked) {
            $query->where('tenant_unit_id', $unitId);
        } else {
            $query->where(fn (Builder $teams): Builder => $teams->where('tenant_unit_id', '!=', $unitId)
                ->orWhereNull('tenant_unit_id'));
        }

        if ($search !== '') {
            $query->whereLike('name', '%'.$search.'%');
        }

        return $query->orderBy('name')->orderBy('id')->paginate(20)->appends($filters);
    }
}
