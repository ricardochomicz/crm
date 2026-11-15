<?php

namespace App\Actions\Commercial;

use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ScopeVisibleCommercialOrdersAction
{
    public function __construct(private ResolveTenantPermissionsAction $permissions) {}

    /** @return list<string>|null Null means unrestricted within the tenant. */
    public function membershipIds(Tenant $tenant, User $user): ?array
    {
        $actor = $this->permissions->membership($user, $tenant) ?? abort(403);
        if (in_array($actor->role, [TenantUserRole::Owner, TenantUserRole::Manager], true)) {
            return null;
        }

        $teams = DB::table('teams')->where('tenant_id', $tenant->id)
            ->where('supervisor_membership_id', $actor->id)->pluck('id');

        return TenantUser::query()->where('tenant_id', $tenant->id)
            ->whereIn('team_id', $teams)->pluck('id')->push($actor->id)->unique()->all();
    }

    /** @return Builder<\App\Models\CommercialOrder> */
    public function apply(Builder $query, Tenant $tenant, User $user): Builder
    {
        $query->where('tenant_id', $tenant->id);
        $visibleIds = $this->membershipIds($tenant, $user);

        return $visibleIds === null ? $query : $query->whereIn('tenant_user_id', $visibleIds);
    }
}
