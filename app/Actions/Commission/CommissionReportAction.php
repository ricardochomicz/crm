<?php

namespace App\Actions\Commission;

use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CommissionReportAction
{
    public function __construct(private ResolveTenantPermissionsAction $permissions) {}

    /** @param array{from: string, to: string, status?: string} $filters */
    public function execute(Tenant $tenant, User $user, array $filters): array
    {
        $membership = $this->permissions->membership($user, $tenant) ?? abort(403);
        abort_unless(in_array($membership->role, [TenantUserRole::Owner, TenantUserRole::Manager], true)
            && $user->can('viewOrders', $tenant), 403);

        $query = DB::table('order_commission_accruals')
            ->where('tenant_id', $tenant->id)
            ->where('is_current', true)
            ->whereBetween('sold_on', [$filters['from'], $filters['to']]);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $summary = (clone $query)
            ->selectRaw('status, COUNT(*) AS items, COALESCE(SUM(quantity), 0) AS lines, COALESCE(SUM(gross_amount), 0) AS gross, COALESCE(SUM(deduction_amount), 0) AS deduction, COALESCE(SUM(net_amount), 0) AS net, COALESCE(SUM(participant_total), 0) AS participants, COALESCE(SUM(company_balance), 0) AS company_balance')
            ->groupBy('status')->orderBy('status')->get();

        // Participant values are snapshots from the current accrual revision, not live rules.
        $participants = [];
        foreach ((clone $query)->whereNotNull('participant_total')->select(['participant_amounts', 'status'])->cursor() as $row) {
            foreach (json_decode($row->participant_amounts, true, flags: JSON_THROW_ON_ERROR) as $participant) {
                $key = ($participant['role'] ?? '').'|'.($participant['tenant_user_id'] ?? $participant['commercial_participant_id'] ?? '');
                if ($key === '|') {
                    continue;
                }
                if (! isset($participants[$key])) {
                    $participants[$key] = [
                        'role' => $participant['role'],
                        'tenant_user_id' => $participant['tenant_user_id'] ?? null,
                        'commercial_participant_id' => $participant['commercial_participant_id'] ?? null,
                        'name' => $participant['name'] ?? 'Participante',
                        'estimated' => '0.00',
                        'eligible' => '0.00',
                    ];
                }
                $field = $row->status === 'ELIGIBLE' ? 'eligible' : ($row->status === 'ESTIMATED' ? 'estimated' : null);
                if ($field !== null) {
                    $participants[$key][$field] = bcadd($participants[$key][$field], (string) $participant['amount'], 2);
                }
            }
        }

        return [
            'period' => ['from' => $filters['from'], 'to' => $filters['to']],
            'summary' => $summary,
            'participants' => array_values($participants),
            'note' => 'Valores apurados por data da venda. Elegibilidade não representa pagamento.',
        ];
    }
}
