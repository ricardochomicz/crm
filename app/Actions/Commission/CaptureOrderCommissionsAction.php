<?php

namespace App\Actions\Commission;

use App\Actions\Commercial\ResolveOrderParticipantsAction;
use App\Models\CommercialOrder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CaptureOrderCommissionsAction
{
    public function __construct(
        private ResolveOrderCommissionRulesAction $rules,
        private CalculateSaleCommissionAction $calculator,
        private ResolveOrderParticipantsAction $participants,
    ) {}

    /**
     * @param  array<int, array{role: string, tenant_user_id?: ?string, commercial_participant_id?: ?string}>|null  $participants
     */
    public function execute(CommercialOrder $order, string $actorId, string $source, ?string $reason = null, ?array $participants = null): int
    {
        if (! in_array($source, ['sale', 'correction', 'manual_review'], true)) {
            throw new InvalidArgumentException('Origem da apuração inválida.');
        }

        if ($source === 'manual_review' && (mb_strlen(trim($reason ?? '')) < 10 || mb_strlen($reason ?? '') > 1000)) {
            throw ValidationException::withMessages(['reason' => 'Informe um motivo entre 10 e 1000 caracteres para a revisão.']);
        }

        if ($participants !== null && $source !== 'manual_review') {
            throw new InvalidArgumentException('Participantes somente podem ser alterados por revisão auditada.');
        }

        return DB::transaction(function () use ($order, $actorId, $source, $reason, $participants): int {
            $order = CommercialOrder::query()->where('tenant_id', $order->tenant_id)->lockForUpdate()->findOrFail($order->id);
            $items = $order->items()->lockForUpdate()->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['order' => 'O pedido precisa possuir linhas para apurar comissões.']);
            }
            if ($participants !== null) {
                $resolved = $this->participants->execute($order->tenant_id, $participants);
                $consultant = collect($resolved)->firstWhere('role', 'CONSULTANT');
                if ($order->tenant_user_id !== null && ($consultant['tenant_user_id'] ?? null) !== $order->tenant_user_id) {
                    throw ValidationException::withMessages(['participants' => 'Mantenha o consultor responsável pela venda na composição da comissão.']);
                }
                $order->participants()->delete();
                $order->participants()->createMany($resolved);
            }
            $query = DB::table('order_commission_accruals')->where('tenant_id', $order->tenant_id)
                ->where('commercial_order_id', $order->id);
            $revision = (int) (clone $query)->max('revision') + 1;

            if ($source === 'sale' && $revision > 1) {
                return $revision - 1;
            }

            if ($source === 'correction') {
                $snapshots = [];
                foreach ((clone $query)->where('is_current', true)->get(['operator_id', 'order_type', 'rule_snapshot']) as $previous) {
                    $snapshots[$this->rules->key((int) $previous->operator_id, $previous->order_type)] = json_decode($previous->rule_snapshot, true, flags: JSON_THROW_ON_ERROR);
                }
            } else {
                $referenceDate = $order->sold_on->toDateString();
                $snapshots = $this->rules->execute($order, $items, $referenceDate);
            }

            (clone $query)->where('is_current', true)->update(['is_current' => false, 'updated_at' => now()]);
            $rows = [];
            foreach ($items as $item) {
                $operatorId = (int) ($item->operator_id ?? $order->operator_id);
                $type = $this->rules->orderType($order, $item);
                $snapshot = $snapshots[$this->rules->key($operatorId, $type)] ?? [
                    'calculation_version' => 1,
                    'basis' => 'monthly_sale_amount',
                    'participants' => [],
                    'issues' => ['sale_rules_not_captured'],
                ];
                $base = (string) BigDecimal::of($item->monthly_unit_amount)->multipliedBy($item->quantity)->toScale(2, RoundingMode::HalfUp);
                $issues = $snapshot['issues'];
                if ($item->quantity !== 1) {
                    $issues[] = 'line_requires_individualization';
                }
                $amounts = [
                    'base_amount' => $base, 'gross_amount' => null, 'deduction_amount' => null,
                    'net_amount' => null, 'participant_total' => null, 'company_balance' => null,
                    'participant_amounts' => [],
                ];
                if ($issues === []) {
                    $amounts = $this->calculator->execute($base, $snapshot['company_percentage'], $snapshot['deduction_percentage'], $snapshot['participants']);
                    $issues = $amounts['issues'];
                    unset($amounts['issues']);
                }
                $activatedOn = $item->status === 'ACTIVE' ? $item->activated_at?->toDateString() : null;
                $rows[] = [
                    ...$amounts,
                    'id' => (string) Str::uuid(), 'tenant_id' => $order->tenant_id,
                    'commercial_order_id' => $order->id, 'source_item_id' => $item->id,
                    'revision' => $revision, 'is_current' => true,
                    'status' => $issues !== [] ? 'PENDING_RULES' : ($activatedOn === null ? 'ESTIMATED' : 'ELIGIBLE'),
                    'source' => $source, 'captured_by' => $actorId, 'reason' => $reason,
                    'sold_on' => $order->sold_on->toDateString(), 'activated_on' => $activatedOn,
                    'operator_id' => $operatorId, 'order_type' => $type,
                    'line_number' => $item->line_number, 'description' => $item->description,
                    'quantity' => $item->quantity,
                    'rule_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                    'participant_amounts' => json_encode($amounts['participant_amounts'], JSON_THROW_ON_ERROR),
                    'issues' => json_encode($issues, JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
            foreach (array_chunk($rows, 200) as $batch) {
                DB::table('order_commission_accruals')->insert($batch);
            }

            return $revision;
        });
    }

    /** @param list<string> $itemIds */
    public function activate(CommercialOrder $order, array $itemIds, string $activatedOn): void
    {
        $query = DB::table('order_commission_accruals')->where('tenant_id', $order->tenant_id)
            ->where('commercial_order_id', $order->id)->where('is_current', true)->whereIn('source_item_id', $itemIds);
        (clone $query)->where('status', 'ESTIMATED')->update(['status' => 'ELIGIBLE', 'updated_at' => now()]);
        $query->update(['activated_on' => $activatedOn, 'updated_at' => now()]);
    }
}
