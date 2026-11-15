<?php

namespace App\Actions\Commission;

use App\Models\CommercialOrder;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class PreviewCommissionRevisionsAction
{
    /**
     * Read-only preview. Never modifies accruals or assumes that a newly created
     * rule necessarily resolves every missing rule on an order.
     *
     * @return array<string, mixed>
     */
    public function execute(Tenant $tenant, int $operatorId, string $orderType, ?string $from = null, ?string $to = null): array
    {
        $query = DB::table('order_commission_accruals as accruals')
            ->where('accruals.tenant_id', $tenant->id)
            ->where('accruals.operator_id', $operatorId)
            ->where('accruals.order_type', $orderType)
            ->where('accruals.is_current', true)
            ->where('accruals.status', 'PENDING_RULES');

        if ($from !== null) {
            $query->whereDate('accruals.sold_on', '>=', $from);
        }
        if ($to !== null) {
            $query->whereDate('accruals.sold_on', '<=', $to);
        }

        $orders = (clone $query)->select('accruals.commercial_order_id', 'accruals.sold_on')
            ->selectRaw('COUNT(*) as pending_items, COALESCE(SUM(accruals.quantity), 0) as pending_lines')
            ->groupBy('accruals.commercial_order_id', 'accruals.sold_on')
            ->orderBy('accruals.sold_on')
            ->orderBy('accruals.commercial_order_id')
            ->limit(101)->get();

        $truncated = $orders->count() > 100;
        $orders = $orders->take(100);

        return [
            'operator_id' => $operatorId,
            'order_type' => $orderType,
            'filters' => ['from' => $from, 'to' => $to],
            'orders' => $orders->map(fn ($order): array => [
                'order_id' => $order->commercial_order_id,
                'sold_on' => $order->sold_on,
                'pending_items' => (int) $order->pending_items,
                'pending_lines' => (int) $order->pending_lines,
            ])->values()->all(),
            'truncated' => $truncated,
            'requires_manual_review' => true,
            'message' => 'Prévia de pedidos pendentes potencialmente afetados. A nova regra não garante a resolução de todas as pendências. Nenhuma comissão foi recalculada.',
        ];
    }
}
