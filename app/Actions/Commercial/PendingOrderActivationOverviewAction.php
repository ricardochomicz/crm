<?php

namespace App\Actions\Commercial;

use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PendingOrderActivationOverviewAction
{
    public function __construct(private ResolveTenantPermissionsAction $permissions) {}

    public function execute(Tenant $tenant, User $user, string $today): array
    {
        $actor = $this->permissions->membership($user, $tenant) ?? abort(403);
        abort_unless($user->can('viewOrders', $tenant), 403);

        $query = DB::table('commercial_order_items as item')
            ->join('commercial_orders as orders', 'orders.id', '=', 'item.commercial_order_id')
            ->where('orders.tenant_id', $tenant->id)
            ->where('item.status', 'PENDING')
            ->whereNull('orders.administratively_completed_at');

        if (! in_array($actor->role, [TenantUserRole::Owner, TenantUserRole::Manager], true)) {
            $teams = DB::table('teams')->where('tenant_id', $tenant->id)
                ->where('supervisor_membership_id', $actor->id)->pluck('id');
            $visibleMembers = TenantUser::query()->where('tenant_id', $tenant->id)
                ->whereIn('team_id', $teams)->pluck('id')->push($actor->id)->unique()->all();
            $query->whereIn('orders.tenant_user_id', $visibleMembers);
        }

        $total = (clone $query)->selectRaw('COUNT(DISTINCT orders.id) as orders_count')
            ->selectRaw('COALESCE(SUM(item.quantity), 0) as lines_count')
            ->selectRaw('COALESCE(SUM(item.quantity * item.monthly_unit_amount), 0) as monthly_total')
            ->first();

        $overdue = (clone $query)
            ->whereNotNull('orders.expected_activation_at')
            ->whereDate('orders.expected_activation_at', '<', $today)
            ->selectRaw('COUNT(DISTINCT orders.id) as orders_count')
            ->selectRaw('COALESCE(SUM(item.quantity), 0) as lines_count')
            ->first();

        return [
            'as_of' => $today,
            'pending' => [
                'orders' => (int) ($total?->orders_count ?? 0),
                'lines' => (int) ($total?->lines_count ?? 0),
                'monthly' => (string) ($total?->monthly_total ?? 0),
            ],
            'overdue' => [
                'orders' => (int) ($overdue?->orders_count ?? 0),
                'lines' => (int) ($overdue?->lines_count ?? 0),
            ],
        ];
    }
}
