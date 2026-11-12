<?php

namespace App\Actions\Commercial;

use App\Actions\Client\ListConsolidatedPortfolioAction;
use App\Models\ClientSeller;
use Illuminate\Support\Facades\DB;

class ListConsultantRenewalPrioritiesAction
{
    public function __construct(private ListConsolidatedPortfolioAction $portfolio) {}

    public function execute(string $tenantId, string $membershipId, int $userId): array
    {
        $clientIds = ClientSeller::query()
            ->where('tenant_id', $tenantId)
            ->where('tenant_user_id', $membershipId)
            ->whereNull('ended_at')
            ->pluck('client_id')
            ->all();

        if (! $clientIds) {
            return ['total_clients' => 0, 'items' => [], 'truncated' => false];
        }

        $items = [];
        $page = 1;
        $lastPage = 1;
        $limitPages = 10;

        do {
            $contracts = $this->portfolio->execute($tenantId, $userId, [
                'per_page' => 100,
                'page' => $page,
            ]);
            $lastPage = $contracts->lastPage();

            foreach ($contracts->items() as $contract) {
                if (! in_array($contract['client_id'], $clientIds, true)
                    || ! in_array($contract['status'], ['upcoming', 'overdue'], true)) {
                    continue;
                }

                $items[] = $contract;
            }

            $page++;
        } while ($page <= $lastPage && $page <= $limitPages);

        $contractIds = array_column($items, 'contract_id');
        $activeRenewals = DB::table('commercial_orders')
            ->join('contract_renewal_opportunities as renewals', 'renewals.source_order_id', '=', 'commercial_orders.id')
            ->join('opportunities', 'opportunities.id', '=', 'renewals.opportunity_id')
            ->where('commercial_orders.tenant_id', $tenantId)
            ->whereIn('commercial_orders.client_contract_id', $contractIds)
            ->where('renewals.tenant_id', $tenantId)
            ->whereNull('opportunities.deleted_at')
            ->whereIn('opportunities.status', ['open', 'won'])
            ->distinct()
            ->pluck('commercial_orders.client_contract_id')
            ->all();

        $items = array_values(array_filter($items, static fn (array $item): bool =>
            ! in_array($item['contract_id'], $activeRenewals, true)
        ));

        usort($items, static fn (array $a, array $b): int =>
            ($a['days_until_renewal'] ?? PHP_INT_MAX) <=> ($b['days_until_renewal'] ?? PHP_INT_MAX)
        );

        return [
            'total_clients' => count(array_unique(array_column($items, 'client_id'))),
            'items' => array_map(static fn (array $item): array => [
                'id' => $item['contract_id'],
                'client_id' => $item['client_id'],
                'client_name' => $item['client_name'],
                'operator_name' => $item['operator_name'],
                'lines' => $item['line_count'],
                'monthly' => $item['monthly_revenue'],
                'renewal_date' => $item['renewal_date'],
                'days_until_renewal' => $item['days_until_renewal'],
                'status' => $item['status'],
            ], $items),
            'truncated' => $page <= $lastPage,
        ];
    }
}
