<?php

namespace App\Actions\Client;

use App\Actions\Opportunity\OpportunityAction;
use App\Models\Client;
use App\Models\CommercialOrder;
use App\Models\OrderType;
use App\Models\ClientOperatorClassification;
use App\Models\ClassificationRenewalRule;
use Carbon\CarbonImmutable;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RenewHistoricalOrderAction
{
    public function __construct(
        private ScopeVisibleClientsAction $visibility,
        private OpportunityAction $opportunities,
        private CalculateContractRenewalAction $calculator,
    ) {}

    public function execute(Tenant $tenant, User $user, string $orderId, array $lineIds): Opportunity
    {
        return DB::transaction(function () use ($tenant, $user, $orderId, $lineIds) {
            $order = CommercialOrder::query()->where('tenant_id', $tenant->id)
                ->whereNull('client_contract_id')->whereNotNull('administratively_completed_at')
                ->with('items')->lockForUpdate()->findOrFail($orderId);
            $client = $order->client_id
                ? $this->visibility->execute(Client::query(), $tenant->id, $user->id)->findOrFail($order->client_id)
                : $this->visibility->execute(Client::query(), $tenant->id, $user->id)
                    ->where('document', $order->customer_document)->firstOrFail();
            $classificationId = ClientOperatorClassification::query()->where('tenant_id', $tenant->id)
                ->where('client_id', $client->id)->where('operator_id', $order->operator_id)->value('classification_id');
            $today = CarbonImmutable::today();
            $rule = ClassificationRenewalRule::query()->where('classification_id', $classificationId)
                ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))
                ->where('is_active', true)->whereDate('valid_from', '<=', $today->toDateString())
                ->where(fn ($q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today->toDateString()))
                ->orderByRaw('CASE WHEN tenant_id IS NULL THEN 1 ELSE 0 END')->orderByDesc('valid_from')->first();
            if (! $rule || $order->items->isEmpty() || $order->items->contains(
                fn ($item) => $item->status !== 'ACTIVE' || ! $item->activated_at || $item->quantity < 1
            )) throw ValidationException::withMessages(['order' => 'O pedido não possui dados completos para calcular a renovação.']);
            $calculated = $this->calculator->execute($order->items, $rule->minimum_months);
            if (CarbonImmutable::parse($calculated['renewal_date'])->greaterThanOrEqualTo($today)) {
                throw ValidationException::withMessages(['order' => 'Este pedido ainda não está vencido.']);
            }
            $existing = DB::table('contract_renewal_opportunities')->where('tenant_id', $tenant->id)
                ->where('source_order_id', $order->id)->pluck('opportunity_id');
            if ($existing->isNotEmpty() && Opportunity::query()->whereIn('id', $existing)
                ->whereIn('status', ['open', 'won'])->exists()) {
                throw ValidationException::withMessages(['order' => 'Já existe uma renovação em andamento ou concluída para este pedido.']);
            }
            $selected = $order->items->whereIn('id', $lineIds)->values();
            if ($selected->count() !== count(array_unique($lineIds)) || $selected->isEmpty()) {
                throw ValidationException::withMessages(['line_ids' => 'Selecione linhas válidas deste pedido.']);
            }
            $type = OrderType::query()->whereRaw('LOWER(name) LIKE ?', ['%renova%'])->first();
            if (! $type) throw ValidationException::withMessages(['order_type' => 'Cadastre um tipo de pedido Renovação antes de iniciar este fluxo.']);
            $items = $selected->map(fn ($item) => [
                'product_id' => $item->product_id,
                'order_type_id' => $type->id,
                'line_number' => $item->line_number,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'monthly_unit_amount' => $item->monthly_unit_amount,
                'one_time_unit_amount' => $item->one_time_unit_amount,
                'notes' => $item->notes,
            ])->all();
            $opportunity = $this->opportunities->create($tenant, $user, [
                'code' => 'REN-' . strtoupper(substr((string) \Illuminate\Support\Str::uuid(), 0, 12)),
                'title' => 'Renovação - ' . mb_substr($client->company_name, 0, 160),
                'client_id' => $client->id,
                'operator_id' => $order->operator_id,
                'order_type_id' => $type->id,
                'source' => 'contract_renewal',
                'description' => 'Renovação originada do pedido ' . $order->code . '.',
                'items' => $items,
            ]);
            DB::table('contract_renewal_opportunities')->insert([
                'tenant_id' => $tenant->id, 'source_order_id' => $order->id,
                'opportunity_id' => $opportunity->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            return $opportunity;
        });
    }
}
