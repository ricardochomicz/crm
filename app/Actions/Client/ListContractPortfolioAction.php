<?php

namespace App\Actions\Client;

use App\Models\Client;
use App\Models\ClientOperatorClassification;
use App\Models\CommercialOrder;
use App\Models\ClassificationRenewalRule;
use App\Models\OperatorClassification;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListContractPortfolioAction
{
    public function __construct(
        private ScopeVisibleClientsAction $visibility,
        private CalculateContractRenewalAction $calculator,
    ) {}

    /** @param array<string, mixed> $filters */
    public function execute(string $tenantId, int $userId, array $filters): LengthAwarePaginator
    {
        $visibleClients = fn (): Builder => $this->visibility->execute(Client::query(), $tenantId, $userId);

        $query = CommercialOrder::query()
            ->where('commercial_orders.tenant_id', $tenantId)
            ->whereNotNull('commercial_orders.administratively_completed_at')
            ->where(function (Builder $orders) use ($visibleClients): void {
                $orders->whereHas('client', function (Builder $clients) use ($visibleClients): void {
                    $clients->whereIn('clients.id', $visibleClients()->select('clients.id'));
                })->orWhere(function (Builder $legacy) use ($visibleClients): void {
                    $legacy->whereNull('commercial_orders.client_id')
                        ->whereExists(
                            $visibleClients()
                                ->selectRaw('1')
                                ->whereColumn('clients.document', 'commercial_orders.customer_document')
                                ->getQuery()
                        );
                });
            })
            ->with(['client:id,company_name,document,operator_id,classification_id', 'operator:id,name', 'items']);

        if (filled($filters['search'] ?? null)) {
            $term = addcslashes(trim($filters['search']), '%_\\');
            $query->where(function (Builder $orders) use ($term): void {
                $orders->where('customer_name', 'like', "%{$term}%")
                    ->orWhere('customer_document', 'like', "%{$term}%")
                    ->orWhereHas('client', fn (Builder $clients) => $clients->where('company_name', 'like', "%{$term}%"));
            });
        }

        if (isset($filters['operator_id'])) {
            $query->where('commercial_orders.operator_id', $filters['operator_id']);
        }

        $paginator = $query
            ->orderByDesc('commercial_orders.administratively_completed_at')
            ->orderByDesc('commercial_orders.id')
            ->paginate($filters['per_page'] ?? 20);

        $orders = $paginator->getCollection();
        $legacyClients = $this->visibility->execute(Client::query(), $tenantId, $userId)
            ->whereIn('document', $orders->whereNull('client_id')->pluck('customer_document')->filter())
            ->get(['id', 'company_name', 'document', 'operator_id', 'classification_id'])
            ->keyBy('document');
        $clientIds = $orders->map(fn (CommercialOrder $order) => $order->client?->id
            ?? ($order->client_id === null ? $legacyClients->get($order->customer_document)?->id : null))
            ->filter()->unique()->values();
        $assignments = ClientOperatorClassification::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('client_id', $clientIds)
            ->get()->keyBy(fn ($entry) => $entry->client_id . ':' . $entry->operator_id);
        $classificationIds = $assignments->pluck('classification_id')->filter()->unique()->values();

        $classifications = OperatorClassification::query()
            ->whereIn('id', $classificationIds)
            ->where(fn (Builder $q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))
            ->get()->keyBy('id');

        $today = CarbonImmutable::today();
        $rules = ClassificationRenewalRule::query()
            ->whereIn('classification_id', $classifications->keys())
            ->where(fn (Builder $q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))
            ->where('is_active', true)
            ->whereDate('valid_from', '<=', $today->toDateString())
            ->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today->toDateString()))
            ->get()
            ->sortByDesc('valid_from')
            ->sortBy(fn ($rule) => $rule->tenant_id === null ? 1 : 0)
            ->groupBy('classification_id');

        $paginator->setCollection($orders->map(function (CommercialOrder $order) use ($legacyClients, $assignments, $classifications, $rules, $today): array {
            $client = $order->client ?? ($order->client_id === null ? $legacyClients->get($order->customer_document) : null);
            $classificationId = $client
                ? $assignments->get($client->id . ':' . $order->operator_id)?->classification_id
                : null;
            $classification = $classifications->get($classificationId);
            $valid = $classification && (int) $classification->operator_id === (int) $order->operator_id;
            $rule = $valid ? $rules->get($classificationId)?->first() : null;

            $row = [
                'order_id' => $order->id,
                'order_code' => $order->code,
                'client_id' => $client?->id,
                'client_name' => $client?->company_name ?? $order->customer_name,
                'operator_id' => $order->operator_id,
                'operator_name' => $order->operator?->name,
                'classification_id' => $valid ? $classificationId : null,
                'classification_name' => $valid ? $classification->name : null,
                'line_count' => (int) $order->items->sum('quantity'),
                'monthly_revenue' => $order->monthly_amount,
                'average_activation_date' => null,
                'renewal_months' => $rule?->minimum_months,
                'renewal_date' => null,
                'days_until_renewal' => null,
                'status' => ! $valid ? 'classification_required' : ($rule ? 'activation_data_required' : 'rule_required'),
            ];

            if (! $rule || $order->items->isEmpty() || $order->items->contains(
                fn ($item) => $item->status !== 'ACTIVE' || ! $item->activated_at || $item->quantity < 1
            )) {
                return $row;
            }

            $calculated = $this->calculator->execute($order->items, $rule->minimum_months);
            $days = (int) $today->diffInDays(CarbonImmutable::parse($calculated['renewal_date']), false);

            return array_merge($row, $calculated, [
                'days_until_renewal' => $days,
                'status' => $days < 0 ? 'overdue' : ($days <= 90 ? 'upcoming' : 'waiting'),
            ]);
        }));

        return $paginator;
    }
}
