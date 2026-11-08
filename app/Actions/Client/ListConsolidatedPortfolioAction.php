<?php

namespace App\Actions\Client;

use App\Models\ClassificationRenewalRule;
use App\Models\Client;
use App\Models\ClientContract;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListConsolidatedPortfolioAction
{
    public function __construct(
        private ScopeVisibleClientsAction $visibility,
        private CalculateContractRenewalAction $calculator,
    ) {}

    /** @param array<string, mixed> $filters */
    public function execute(string $tenantId, int $userId, array $filters): LengthAwarePaginator
    {
        $visible = $this->visibility->execute(Client::query(), $tenantId, $userId)->select('clients.id');

        $query = ClientContract::query()
            ->where('client_contracts.tenant_id', $tenantId)
            ->whereIn('client_contracts.client_id', $visible)
            ->with([
                'client:id,company_name,document',
                'operator:id,name',
                'classification:id,name,operator_id',
                'orders' => fn ($orders) => $orders->whereNotNull('administratively_completed_at')
                    ->with('items'),
            ])
            ->whereHas('orders', fn (Builder $orders) => $orders->whereNotNull('administratively_completed_at'));

        if (filled($filters['search'] ?? null)) {
            $search = addcslashes(trim($filters['search']), '%_\\');
            $query->whereHas('client', fn (Builder $clients) => $clients
                ->where(fn (Builder $names) => $names->where('company_name', 'like', "%{$search}%")
                    ->orWhere('document', 'like', "%{$search}%")));
        }

        if (isset($filters['operator_id'])) {
            $query->where('client_contracts.operator_id', $filters['operator_id']);
        }

        $page = $query->orderBy('client_contracts.created_at')->orderBy('client_contracts.id')
            ->paginate($filters['per_page'] ?? 20);

        $today = CarbonImmutable::today();
        $rules = ClassificationRenewalRule::query()
            ->whereIn('classification_id', $page->getCollection()->pluck('classification_id')->filter()->unique())
            ->where(fn (Builder $q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))
            ->where('is_active', true)
            ->whereDate('valid_from', '<=', $today->toDateString())
            ->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today->toDateString()))
            ->orderByRaw('CASE WHEN tenant_id IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('valid_from')
            ->get()->groupBy('classification_id');

        $page->setCollection($page->getCollection()->map(function (ClientContract $contract) use ($today, $rules): array {
            $items = $contract->orders->flatMap(fn ($order) => $order->items);
            $active = $items->filter(fn ($item) => $item->status === 'ACTIVE');
            $lines = (int) $active->sum('quantity');
            $revenueCents = $active->sum(fn ($item) => (int) round((float) $item->monthly_unit_amount * 100) * $item->quantity);
            $validClassification = $contract->classification
                && (int) $contract->classification->operator_id === (int) $contract->operator_id;
            $rule = $validClassification ? $rules->get($contract->classification_id)?->first() : null;

            $row = [
                'contract_id' => $contract->id,
                'order_id' => null,
                'order_code' => null,
                'order_count' => $contract->orders->count(),
                'client_id' => $contract->client_id,
                'client_name' => $contract->client?->company_name,
                'operator_id' => $contract->operator_id,
                'operator_name' => $contract->operator?->name,
                'classification_id' => $contract->classification_id,
                'classification_name' => $contract->classification?->name,
                'line_count' => $lines,
                'monthly_revenue' => number_format($revenueCents / 100, 2, '.', ''),
                'average_activation_date' => null,
                'renewal_months' => $rule?->minimum_months,
                'renewal_date' => null,
                'days_until_renewal' => null,
                'renewal_policy' => $contract->renewal_policy,
                'status' => 'policy_pending',
            ];

            if ($contract->renewal_policy !== ClientContract::POLICY_WEIGHTED_AVERAGE) {
                return $row;
            }
            if (! $validClassification) {
                $row['status'] = 'classification_required';
                return $row;
            }
            if (! $rule) {
                $row['status'] = 'rule_required';
                return $row;
            }
            if ($active->isEmpty() || $active->contains(fn ($item) => ! $item->activated_at || $item->quantity < 1)) {
                $row['status'] = 'activation_data_required';
                return $row;
            }

            $calculated = $this->calculator->execute($active->values(), $rule->minimum_months);
            $days = (int) $today->diffInDays(CarbonImmutable::parse($calculated['renewal_date']), false);

            return array_merge($row, $calculated, [
                'days_until_renewal' => $days,
                'status' => $days < 0 ? 'overdue' : ($days <= 90 ? 'upcoming' : 'waiting'),
            ]);
        }));

        return $page;
    }
}
