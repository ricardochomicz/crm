<?php

namespace App\Actions\Commercial;

use App\Models\CalendarEvent;
use App\Models\CommercialGoal;
use App\Models\CommercialOrder;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Enums\TenantUserRole;

class ConsultantProductivityAction
{
    public function overview(Tenant $tenant, User $user, array $filters): array
    {
        abort_unless($user->can('viewTeamCalendar', $tenant), 403);
        $actor = TenantUser::query()->where('tenant_id', $tenant->id)->where('user_id', $user->id)->where('status', 'ACTIVE')->firstOrFail();
        $from = $filters['from'];
        $to = $filters['to'];
        $isManager = in_array($actor->role, [TenantUserRole::Owner, TenantUserRole::Manager], true);
        $members = TenantUser::query()->with('user:id,name')->where('tenant_id', $tenant->id)->where('status', 'ACTIVE');
        if (! $isManager) {
            $teams = $actor->supervisedTeams()->where('tenant_id', $tenant->id)->pluck('id');
            $members->where(fn ($q) => $q->whereKey($actor->id)->orWhereIn('team_id', $teams));
        }
        if (! empty($filters['team_id'])) $members->where('team_id', $filters['team_id']);
        if (! empty($filters['consultant_id'])) $members->whereKey($filters['consultant_id']);
        $members = $members->orderBy('id')->limit(500)->get();
        $ids = $members->pluck('id');
        if ($ids->isEmpty()) return ['data' => [], 'totals' => $this->emptyTotals()];

        $activities = CalendarEvent::query()->where('tenant_id', $tenant->id)->whereIn('tenant_user_id', $ids)
            ->whereIn('type', ['task', 'reminder'])
            ->where(function ($query) use ($from, $to) {
                $query->whereBetween('completed_at', [$from.' 00:00:00', $to.' 23:59:59'])
                    ->orWhereBetween('ends_at', [$from.' 00:00:00', $to.' 23:59:59']);
            })
            ->select('tenant_user_id')
            ->selectRaw("SUM(CASE WHEN status = 'completed' AND completed_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS completed", [$from.' 00:00:00', $to.' 23:59:59'])
            ->selectRaw("SUM(CASE WHEN status = 'pending' AND ends_at < ? AND ends_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS overdue", [now(), $from.' 00:00:00', $to.' 23:59:59'])
            ->groupBy('tenant_user_id')->get()->keyBy('tenant_user_id');

        $opportunities = Opportunity::query()->where('tenant_id', $tenant->id)->whereIn('tenant_user_id', $ids)
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->select('tenant_user_id')->selectRaw("SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_count")
            ->selectRaw('COUNT(*) AS total_count')->groupBy('tenant_user_id')->get()->keyBy('tenant_user_id');

        $orders = CommercialOrder::query()->where('tenant_id', $tenant->id)->whereIn('tenant_user_id', $ids)
            ->whereBetween('sold_on', [$from, $to])->select('tenant_user_id')
            ->selectRaw('COUNT(*) AS orders_count')->selectRaw('COALESCE(SUM(monthly_amount), 0) AS monthly_amount')
            ->groupBy('tenant_user_id')->get()->keyBy('tenant_user_id');

        $rows = $members->map(function ($member) use ($activities, $opportunities, $orders) {
            $id = $member->id;
            return [
                'id' => $id,
                'name' => $member->user?->name ?? 'Usuário',
                'completed' => (int) ($activities->get($id)?->completed ?? 0),
                'overdue' => (int) ($activities->get($id)?->overdue ?? 0),
                'opportunities' => (int) ($opportunities->get($id)?->total_count ?? 0),
                'open_opportunities' => (int) ($opportunities->get($id)?->open_count ?? 0),
                'orders' => (int) ($orders->get($id)?->orders_count ?? 0),
                'monthly_amount' => (float) ($orders->get($id)?->monthly_amount ?? 0),
            ];
        })->values();

        $totals = $this->emptyTotals();
        foreach ($rows as $row) foreach ($totals as $key => $value) $totals[$key] += $row[$key];

        return ['data' => $rows, 'totals' => $totals];
    }

    /** Existing commission goal configurations, not monthly sales targets. */
    public function goalConfigurations(Tenant $tenant, User $user, array $filters): array
    {
        $overview = $this->overview($tenant, $user, $filters);
        $memberIds = collect($overview['data'])->pluck('id')->all();
        if ($memberIds === []) return ['data' => []];

        $goals = CommercialGoal::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->where('starts_on', '<=', $filters['to'])
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $filters['from']))
            ->where(function ($query) use ($memberIds) {
                $query->where(function ($company) {
                    $company->where('target_scope', 'COMPANY')->whereNull('responsible_membership_id');
                })->orWhere(function ($personal) use ($memberIds) {
                    $personal->where('target_scope', 'CONSULTANT')->whereIn('responsible_membership_id', $memberIds);
                });
            })
            ->with('tiers')
            ->orderBy('starts_on')
            ->limit(500)
            ->get();

        return ['data' => $goals->map(fn (CommercialGoal $goal) => [
            'id' => $goal->id,
            'operator_id' => $goal->operator_id,
            'order_type' => $goal->order_type,
            'metric' => $goal->metric,
            'target_scope' => $goal->target_scope,
            'responsible_membership_id' => $goal->responsible_membership_id,
            'starts_on' => $goal->starts_on?->format('Y-m-d'),
            'ends_on' => $goal->ends_on?->format('Y-m-d'),
            'tiers' => $goal->tiers->map(fn ($tier) => [
                'minimum' => (float) $tier->minimum,
                'percentage' => (float) $tier->percentage,
            ])->values()->all(),
        ])->values()->all()];
    }

    public function records(Tenant $tenant, User $user, array $filters): array
    {
        // Reuse the overview's tenant, role and team visibility checks.
        $overview = $this->overview($tenant, $user, [
            'from' => $filters['from'], 'to' => $filters['to'],
            'consultant_id' => $filters['consultant_id'],
        ]);
        abort_if(empty($overview['data']), 404, 'Consultor não encontrado ou sem acesso.');

        $id = $filters['consultant_id'];
        $from = $filters['from'].' 00:00:00';
        $to = $filters['to'].' 23:59:59';
        $type = $filters['type'];

        $query = match ($type) {
            'activities' => CalendarEvent::query()->where('tenant_id', $tenant->id)
                ->where('tenant_user_id', $id)->whereIn('type', ['task', 'reminder'])
                ->where(function ($q) use ($from, $to) {
                    $q->whereBetween('completed_at', [$from, $to])
                        ->orWhereBetween('ends_at', [$from, $to]);
                })->select(['id', 'title', 'status', 'ends_at', 'completed_at', 'created_at']),
            'opportunities' => Opportunity::query()->where('tenant_id', $tenant->id)
                ->where('tenant_user_id', $id)->whereBetween('created_at', [$from, $to])
                ->select(['id', 'title', 'code', 'status', 'created_at']),
            'orders' => CommercialOrder::query()->where('tenant_id', $tenant->id)
                ->where('tenant_user_id', $id)->whereBetween('sold_on', [$filters['from'], $filters['to']])
                ->select(['id', 'code', 'customer_name', 'status', 'monthly_amount', 'sold_on', 'created_at']),
        };

        return $query->orderByDesc('created_at')->paginate(10)->toArray();
    }

    private function emptyTotals(): array
    {
        return ['completed' => 0, 'overdue' => 0, 'opportunities' => 0, 'open_opportunities' => 0, 'orders' => 0, 'monthly_amount' => 0];
    }
}
