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

    /** @return Builder<\App\Models\CommercialOrder> */
    public function apply(Builder $query, Tenant $tenant, User $user): Builder
    {
        $actor = $this->permissions->membership($user, $tenant) ?? abort(403);
        $query->where('tenant_id', $tenant->id);
        if (in_array($actor->role, [TenantUserRole::Owner, TenantUserRole::Manager], true)) {
            return $query;
        }

        $teams = DB::table('teams')->where('tenant_id', $tenant->id)
            ->where('supervisor_membership_id', $actor->id)->pluck('id');
        $visibleIds = TenantUser::query()->where('tenant_id', $tenant->id)
            ->whereIn('team_id', $teams)->pluck('id')->push($actor->id)->unique()->all();

        return $query->whereIn('tenant_user_id', $visibleIds);
    }
}
