<?php

namespace App\Actions\Commercial;

use App\Models\CalendarEvent;
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
            ->whereIn('type', ['task', 'reminder'])->where('starts_at', '<=', $to.' 23:59:59')
            ->where('ends_at', '>=', $from.' 00:00:00')
            ->select('tenant_user_id')
            ->selectRaw("SUM(CASE WHEN status = 'completed' AND completed_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS completed", [$from.' 00:00:00', $to.' 23:59:59']")
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

    private function emptyTotals(): array
    {
        return ['completed' => 0, 'overdue' => 0, 'opportunities' => 0, 'open_opportunities' => 0, 'orders' => 0, 'monthly_amount' => 0];
    }
}
