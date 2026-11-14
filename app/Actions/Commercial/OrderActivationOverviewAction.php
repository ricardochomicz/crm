<?php

namespace App\Actions\Commercial;

use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderActivationOverviewAction
{
    public function __construct(private ResolveTenantPermissionsAction $permissions) {}

    public function execute(Tenant $tenant, User $user, string $from, string $to, ?string $orderTypeId = null): array
    {
        $actor = $this->permissions->membership($user, $tenant) ?? abort(403);
        abort_unless($user->can('viewOrders', $tenant), 403);

        $query = DB::table('commercial_order_items as item')
            ->join('commercial_orders as orders', 'orders.id', '=', 'item.commercial_order_id')
            ->leftJoin('order_types as types', 'types.id', '=', 'item.order_type_id')
            ->where('orders.tenant_id', $tenant->id)
            ->where('item.status', 'ACTIVE')
            ->whereBetween('item.activated_at', [$from, $to]);

        if (! in_array($actor->role, [TenantUserRole::Owner, TenantUserRole::Manager], true)) {
            $teams = DB::table('teams')->where('tenant_id', $tenant->id)
                ->where('supervisor_membership_id', $actor->id)->pluck('id');
            $visibleMembers = TenantUser::query()->where('tenant_id', $tenant->id)
                ->whereIn('team_id', $teams)->pluck('id')->push($actor->id)->unique()->all();
            $query->whereIn('orders.tenant_user_id', $visibleMembers);
        }

        if ($orderTypeId !== null) {
            $query->where('item.order_type_id', $orderTypeId);
        }

        $rows = (clone $query)
            ->select('item.order_type_id', 'types.name as order_type_name')
            ->selectRaw('SUM(item.quantity) as lines')
            ->selectRaw('COALESCE(SUM(item.quantity * item.monthly_unit_amount), 0) as monthly')
            ->groupBy('item.order_type_id', 'types.name')
            ->orderBy('types.name')
            ->get();

        return [
            'period' => ['from' => $from, 'to' => $to],
            'total' => [
                'lines' => (int) $rows->sum('lines'),
                'monthly' => number_format((float) $rows->sum('monthly'), 2, '.', ''),
            ],
            'by_type' => $rows->map(fn ($row) => [
                'order_type_id' => $row->order_type_id,
                'name' => $row->order_type_name ?? 'Sem tipo definido',
                'lines' => (int) $row->lines,
                'monthly' => (string) $row->monthly,
            ])->values()->all(),
        ];
    }
}
