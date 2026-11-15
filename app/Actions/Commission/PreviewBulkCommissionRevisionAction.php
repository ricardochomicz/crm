<?php

namespace App\Actions\Commission;

use App\Models\CommercialOrder;
use Illuminate\Support\Facades\DB;

class PreviewBulkCommissionRevisionAction
{
    public function __construct(
        private ResolveOrderCommissionRulesAction $rules,
        private CalculateSaleCommissionAction $calculator,
    ) {}

    /** @return array<string, mixed> */
    public function execute(CommercialOrder $order, int $operatorId, string $orderType): array
    {
        $current = DB::table('order_commission_accruals')
            ->where('tenant_id', $order->tenant_id)
            ->where('commercial_order_id', $order->id)
            ->where('is_current', true)->get();
        $matching = $current->filter(fn ($row) => (int) $row->operator_id === $operatorId
            && $row->order_type === $orderType);
        $result = [
            'order_id' => $order->id,
            'sold_on' => $order->sold_on->toDateString(),
            'status' => 'ready',
            'reason' => null,
            'current' => $this->totals($current),
            'projected' => null,
        ];

        if ($matching->isEmpty() || ! $matching->contains('status', 'PENDING_RULES')) {
            return [...$result, 'status' => 'skipped', 'reason' => 'no_matching_pending_commission'];
        }

        $items = $order->items()->get();
        if ($items->isEmpty() || $items->count() !== $current->count()
            || $items->pluck('id')->sort()->values()->all() !== $current->pluck('source_item_id')->sort()->values()->all()) {
            return [...$result, 'status' => 'skipped', 'reason' => 'accrual_items_mismatch'];
        }

        $snapshots = $this->rules->execute($order, $items, $order->sold_on->toDateString());
        $totals = ['base_amount' => '0.00', 'gross_amount' => '0.00', 'deduction_amount' => '0.00',
            'net_amount' => '0.00', 'participant_total' => '0.00', 'company_balance' => '0.00'];
        $participants = [];

        foreach ($items as $item) {
            $key = $this->rules->key((int) ($item->operator_id ?? $order->operator_id), $this->rules->orderType($order, $item));
            $snapshot = $snapshots[$key] ?? null;
            if ($snapshot === null || ($snapshot['issues'] ?? []) !== [] || $item->quantity !== 1) {
                return [...$result, 'status' => 'skipped', 'reason' => 'unresolved_rules_or_nonindividualized_lines'];
            }
            $amounts = $this->calculator->execute(
                (string) $item->monthly_unit_amount,
                (string) $snapshot['company_percentage'],
                (string) $snapshot['deduction_percentage'],
                $snapshot['participants'],
            );
            if ($amounts['issues'] !== []) {
                return [...$result, 'status' => 'skipped', 'reason' => 'invalid_commission_balance'];
            }
            foreach (array_keys($totals) as $field) {
                $totals[$field] = bcadd($totals[$field], $amounts[$field], 2);
            }
            foreach ($amounts['participant_amounts'] as $participant) {
                $id = ($participant['role'] ?? '').':'.($participant['tenant_user_id'] ?? $participant['commercial_participant_id'] ?? '');
                if (! isset($participants[$id])) {
                    $participants[$id] = ['role' => $participant['role'], 'name' => $participant['name'], 'amount' => '0.00'];
                }
                $participants[$id]['amount'] = bcadd($participants[$id]['amount'], $participant['amount'], 2);
            }
        }

        return [...$result, 'projected' => [...$totals, 'participants' => array_values($participants)]];
    }

    /** @param \Illuminate\Support\Collection<int, object> $rows */
    private function totals($rows): array
    {
        $fields = ['base_amount', 'gross_amount', 'deduction_amount', 'net_amount', 'participant_total', 'company_balance'];
        $totals = array_fill_keys($fields, '0.00');
        $participants = [];
        foreach ($rows as $row) {
            foreach ($fields as $field) {
                if ($row->{$field} !== null) {
                    $totals[$field] = bcadd($totals[$field], (string) $row->{$field}, 2);
                }
            }
            foreach (json_decode($row->participant_amounts ?? '[]', true) ?: [] as $participant) {
                $id = ($participant['role'] ?? '').':'.($participant['tenant_user_id'] ?? $participant['commercial_participant_id'] ?? '');
                if (! isset($participants[$id])) {
                    $participants[$id] = ['role' => $participant['role'], 'name' => $participant['name'], 'amount' => '0.00'];
                }
                $participants[$id]['amount'] = bcadd($participants[$id]['amount'], (string) $participant['amount'], 2);
            }
        }
        return [...$totals, 'participants' => array_values($participants)];
    }
}
