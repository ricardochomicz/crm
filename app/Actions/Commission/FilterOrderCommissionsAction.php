<?php

namespace App\Actions\Commission;

use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\TenantUserRole;
use App\Models\CommercialOrder;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FilterOrderCommissionsAction
{
    public function __construct(private ResolveTenantPermissionsAction $permissions) {}

    public function findOrder(Tenant $tenant, User $user, string $orderId): CommercialOrder
    {
        $actor = $this->permissions->membership($user, $tenant) ?? abort(403);
        $query = CommercialOrder::query()->where('tenant_id', $tenant->id);
        if (! in_array($actor->role, [TenantUserRole::Owner, TenantUserRole::Manager], true)) {
            $teamIds = DB::table('teams')->where('tenant_id', $tenant->id)
                ->where('supervisor_membership_id', $actor->id)->select('id');
            $memberIds = TenantUser::query()->where('tenant_id', $tenant->id)
                ->where('status', 'ACTIVE')->whereIn('team_id', $teamIds)->pluck('id')->push($actor->id);
            $query->whereIn('tenant_user_id', $memberIds);
        }

        return $query->findOrFail($orderId);
    }

    /** @param array{revision?: ?int, status?: ?string, per_page?: ?int, page?: ?int} $filters */
    public function query(CommercialOrder $order, array $filters): Builder
    {
        $query = DB::table('order_commission_accruals')->where('tenant_id', $order->tenant_id)
            ->where('commercial_order_id', $order->id);
        if (isset($filters['revision'])) {
            $query->where('revision', $filters['revision']);
        } else {
            $query->where('is_current', true);
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }
}
