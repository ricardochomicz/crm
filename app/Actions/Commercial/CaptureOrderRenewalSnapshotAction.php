<?php

namespace App\Actions\Commercial;

use App\Actions\Client\CalculateContractRenewalAction;
use App\Actions\Client\ResolveActiveRenewalRuleAction;
use App\Models\ClientOperatorClassification;
use App\Models\CommercialOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CaptureOrderRenewalSnapshotAction
{
    public function __construct(
        private ResolveActiveRenewalRuleAction $rules,
        private CalculateContractRenewalAction $calculator,
    ) {}

    /**
     * Capture once at completion. Missing historical classification/rule is
     * deliberately left unresolved instead of inventing contractual terms.
     *
     * @param Collection<int, \App\Models\CommercialOrderItem> $items
     */
    public function execute(CommercialOrder $order, Collection $items): void
    {
        if ($order->renewal_snapshot_at !== null || $order->client_id === null) {
            return;
        }

        if ($items->isEmpty() || $items->contains(
            fn ($item) => $item->status !== 'ACTIVE' || ! $item->activated_at || (int) $item->quantity < 1
        )) {
            return;
        }

        $classificationId = ClientOperatorClassification::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('client_id', $order->client_id)
            ->where('operator_id', $order->operator_id)
            ->value('classification_id');

        $rule = $this->rules->execute(
            $order->tenant_id,
            $classificationId ? (int) $classificationId : null,
            CarbonImmutable::today()
        );

        if ($rule === null) {
            return;
        }

        $calculated = $this->calculator->execute($items, $rule->minimum_months);

        $order->update([
            'renewal_rule_id' => $rule->id,
            'renewal_months_snapshot' => $rule->minimum_months,
            'renewal_date_snapshot' => $calculated['renewal_date'],
            'renewal_snapshot_at' => now(),
        ]);
    }
}
