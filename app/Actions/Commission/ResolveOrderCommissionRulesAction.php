<?php

namespace App\Actions\Commission;

use App\Models\CommercialOrder;
use App\Models\CommercialOrderItem;
use App\Models\CommercialParticipantCommission;
use App\Models\CommissionTable;
use App\Models\TeamCommissionRule;
use App\Models\TenantUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ResolveOrderCommissionRulesAction
{
    /**
     * @param  Collection<int, CommercialOrderItem>  $items
     * @return array<string, array<string, mixed>>
     */
    public function execute(CommercialOrder $order, Collection $items, string $referenceDate): array
    {
        $participants = $order->participants()->get()->map(fn ($participant): array => [
            'role' => $participant->role,
            'tenant_user_id' => $participant->tenant_user_id,
            'commercial_participant_id' => $participant->commercial_participant_id,
            'name' => $participant->participant_name,
        ])->all();

        if ($participants === [] && $order->tenant_user_id !== null) {
            $consultant = TenantUser::query()->where('tenant_id', $order->tenant_id)
                ->with('user:id,name')->find($order->tenant_user_id);
            if ($consultant !== null) {
                $participants[] = [
                    'role' => 'CONSULTANT', 'tenant_user_id' => $consultant->id,
                    'commercial_participant_id' => null, 'name' => $consultant->user->name,
                ];
            }
        }

        $operatorIds = $items->map(fn (CommercialOrderItem $item): int => (int) ($item->operator_id ?? $order->operator_id))->unique();
        $types = $items->map(fn (CommercialOrderItem $item): ?string => $this->orderType($order, $item))->filter()->unique();
        $companyRules = CommissionTable::query()->where('tenant_id', $order->tenant_id)
            ->whereIn('operator_id', $operatorIds)->whereIn('order_type', $types)->where('is_active', true)->get();
        $teamRules = TeamCommissionRule::query()->where('tenant_id', $order->tenant_id)
            ->whereIn('operator_id', $operatorIds)->whereIn('order_type', $types)->where('is_active', true)
            ->where('starts_on', '<=', $referenceDate)
            ->where(fn (Builder $query): Builder => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $referenceDate))->get();
        $overrides = CommercialParticipantCommission::query()->where('tenant_id', $order->tenant_id)
            ->whereIn('commercial_participant_id', array_column($participants, 'commercial_participant_id'))
            ->whereIn('operator_id', $operatorIds)->whereIn('order_type', $types)->where('is_active', true)
            ->where('starts_on', '<=', $referenceDate)
            ->where(fn (Builder $query): Builder => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $referenceDate))->get();
        $defaultDeduction = (string) (DB::table('tenant_commission_settings')->where('tenant_id', $order->tenant_id)
            ->value('default_deduction_percentage') ?? '0.00');
        $snapshots = [];

        foreach ($items as $item) {
            $operatorId = (int) ($item->operator_id ?? $order->operator_id);
            $type = $this->orderType($order, $item);
            $key = $this->key($operatorId, $type);
            if (isset($snapshots[$key])) {
                continue;
            }

            $issues = $participants === [] ? ['participants_missing'] : [];
            $matches = $companyRules->where('operator_id', $operatorId)->where('order_type', $type);
            $company = $matches->count() === 1 ? $matches->first() : null;
            if ($company === null) {
                $issues[] = $matches->isEmpty() ? 'company_rule_missing' : 'company_rule_ambiguous';
            }
            $resolvedParticipants = [];
            foreach ($participants as $participant) {
                $matches = $participant['commercial_participant_id'] === null
                    ? collect()
                    : $overrides->where('commercial_participant_id', $participant['commercial_participant_id'])
                        ->where('operator_id', $operatorId)->where('order_type', $type);
                $source = 'participant_override';
                if ($matches->isEmpty()) {
                    $source = 'team_rule';
                    $matches = $teamRules->where('operator_id', $operatorId)->where('order_type', $type)
                        ->where('commercial_role', $participant['role']);
                }
                $rule = $matches->count() === 1 ? $matches->first() : null;
                if ($rule === null) {
                    $issues[] = ($matches->isEmpty() ? 'participant_rule_missing:' : 'participant_rule_ambiguous:').$participant['role'];
                }
                $resolvedParticipants[] = [
                    ...$participant, 'percentage' => $rule?->percentage,
                    'rule_id' => $rule?->id, 'rule_source' => $source,
                ];
            }
            $snapshots[$key] = [
                'calculation_version' => 1,
                'basis' => 'monthly_sale_amount',
                'rounding' => 'half_up_per_line',
                'reference_date' => $referenceDate,
                'captured_at' => now()->toIso8601String(),
                'operator_id' => $operatorId,
                'order_type' => $type,
                'company_rule_id' => $company?->id,
                'company_percentage' => $company?->percentage,
                'deduction_percentage' => $company?->deduction_percentage ?? $defaultDeduction,
                'deduction_source' => $company?->deduction_percentage === null ? 'tenant_default' : 'company_rule',
                'participants' => $resolvedParticipants,
                'issues' => $issues,
            ];
        }

        return $snapshots;
    }

    public function orderType(CommercialOrder $order, CommercialOrderItem $item): ?string
    {
        return $item->order_type_id ?? $order->order_type;
    }

    public function key(int $operatorId, ?string $orderType): string
    {
        return $operatorId.':'.($orderType ?? '');
    }
}
