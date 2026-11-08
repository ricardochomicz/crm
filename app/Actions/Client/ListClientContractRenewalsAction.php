<?php

namespace App\Actions\Client;

use App\Models\Client;
use App\Models\CommercialOrder;
use App\Models\ClassificationRenewalRule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ListClientContractRenewalsAction
{
    public function __construct(private CalculateContractRenewalAction $calculator) {}

    /**
     * Cada pedido concluído representa um contrato integral independente.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(Client $client): Collection
    {
        $orders = CommercialOrder::query()
            ->where('tenant_id', $client->tenant_id)
            ->where(function ($query) use ($client) {
                $query->where('client_id', $client->id)
                    ->orWhere(function ($legacy) use ($client) {
                        $legacy->whereNull('client_id')
                            ->where('customer_document', $client->document);
                    });
            })
            ->whereNotNull('administratively_completed_at')
            ->with(['items', 'operator:id,name'])
            ->orderByDesc('administratively_completed_at')
            ->get();

        return $orders->map(function (CommercialOrder $order) use ($client): array {
            $result = [
                'order_id' => $order->id,
                'order_code' => $order->code,
                'operator_id' => $order->operator_id,
                'operator_name' => $order->operator?->name,
                'classification_id' => null,
                'renewal_months' => null,
                'average_activation_date' => null,
                'renewal_date' => null,
                'line_count' => $order->items->sum('quantity'),
                'status' => 'classification_required',
            ];

            // A classificação do cliente só é aplicável ao contrato da mesma operadora.
            if (! $client->classification_id || (int) $client->operator_id !== (int) $order->operator_id) {
                return $result;
            }

            $rule = ClassificationRenewalRule::query()
                ->where('classification_id', $client->classification_id)
                ->where(function ($query) use ($client) {
                    $query->where('tenant_id', $client->tenant_id)->orWhereNull('tenant_id');
                })
                ->where('is_active', true)
                ->whereDate('valid_from', '<=', CarbonImmutable::today()->toDateString())
                ->where(function ($query) {
                    $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', CarbonImmutable::today()->toDateString());
                })
                ->orderByRaw('CASE WHEN tenant_id IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('valid_from')
                ->first();

            $result['classification_id'] = $client->classification_id;

            if (! $rule) {
                $result['status'] = 'rule_required';

                return $result;
            }

            if ($order->items->isEmpty() || $order->items->contains(fn ($item) => $item->status !== 'ACTIVE' || ! $item->activated_at || $item->quantity < 1)) {
                $result['status'] = 'activation_data_required';

                return $result;
            }

            $calculated = $this->calculator->execute($order->items, $rule->minimum_months);
            $renewalDate = CarbonImmutable::parse($calculated['renewal_date']);
            $daysRemaining = CarbonImmutable::today()->diffInDays($renewalDate, false);

            return array_merge($result, $calculated, [
                'renewal_months' => $rule->minimum_months,
                'days_until_renewal' => (int) $daysRemaining,
                'status' => $daysRemaining < 0 ? 'overdue' : ($daysRemaining <= 90 ? 'upcoming' : 'waiting'),
            ]);
        });
    }
}
