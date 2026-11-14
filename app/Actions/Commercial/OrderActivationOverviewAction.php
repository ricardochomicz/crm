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

    public function execute(Tenant $tenant, User $user, string $from, string $to, array $orderTypeIds = []): array
    {
        $actor = $this->permissions->membership($user, $tenant) ?? abort(403);
        abort_unless($user->can('viewOrders', $tenant), 403);

        $previousFrom = \Carbon\CarbonImmutable::parse($from)->subMonthNoOverflow();
        $previousTo = \Carbon\CarbonImmutable::parse($to)->subMonthNoOverflow();
        $query = DB::table('commercial_order_items as item')
            ->join('commercial_orders as orders', 'orders.id', '=', 'item.commercial_order_id')
            ->leftJoin('order_types as types', 'types.id', '=', 'item.order_type_id')
            ->where('orders.tenant_id', $tenant->id)
            ->where('item.status', 'ACTIVE')
            ->whereBetween('item.activated_at', [$previousFrom->toDateString(), $to]);

        if (! in_array($actor->role, [TenantUserRole::Owner, TenantUserRole::Manager], true)) {
            $teams = DB::table('teams')->where('tenant_id', $tenant->id)
                ->where('supervisor_membership_id', $actor->id)->pluck('id');
            $visibleMembers = TenantUser::query()->where('tenant_id', $tenant->id)
                ->whereIn('team_id', $teams)->pluck('id')->push($actor->id)->unique()->all();
            $query->whereIn('orders.tenant_user_id', $visibleMembers);
        }

        if ($orderTypeIds !== []) {
            $query->whereIn('item.order_type_id', $orderTypeIds);
        }

        $rows = (clone $query)->whereBetween('item.activated_at', [$from, $to])
            ->select('item.order_type_id', 'types.name as order_type_name')
            ->selectRaw('SUM(item.quantity) as activated_lines')
            ->selectRaw('COALESCE(SUM(item.quantity * item.monthly_unit_amount), 0) as monthly_total')
            ->groupBy('item.order_type_id', 'types.name')
            ->orderBy('types.name')
            ->get();

        $previous = (clone $query)
            ->whereBetween('item.activated_at', [$previousFrom->toDateString(), $previousTo->toDateString()])
            ->selectRaw('COALESCE(SUM(item.quantity), 0) as activated_lines')
            ->selectRaw('COALESCE(SUM(item.quantity * item.monthly_unit_amount), 0) as monthly_total')
            ->first();

        return [
            'previous_period' => ['from' => $previousFrom->toDateString(), 'to' => $previousTo->toDateString()],
            'previous_total' => [
                'lines' => (int) ($previous?->activated_lines ?? 0),
                'monthly' => (string) ($previous?->monthly_total ?? 0),
            ],
            'period' => ['from' => $from, 'to' => $to],
            'total' => [
                'lines' => (int) $rows->sum('activated_lines'),
                'monthly' => number_format((float) $rows->sum('monthly_total'), 2, '.', ''),
            ],
            'by_type' => $rows->map(fn ($row) => [
                'order_type_id' => $row->order_type_id,
                'name' => $row->order_type_name ?? 'Sem tipo definido',
                'lines' => (int) $row->activated_lines,
                'monthly' => (string) $row->monthly_total,
            ])->values()->all(),
        ];
    }
}
