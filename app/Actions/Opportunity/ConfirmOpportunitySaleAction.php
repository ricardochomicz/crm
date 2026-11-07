<?php

namespace App\Actions\Opportunity;

use App\Enums\TenantUserRole;
use App\Models\CommercialOrder;
use App\Models\Opportunity;
use App\Models\OrderStatus;
use Illuminate\Support\Str;
use App\Models\Tenant;
use App\Models\User;
use App\Actions\Tenant\ResolveTenantPermissionsAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmOpportunitySaleAction
{
    public function __construct(private ResolveTenantPermissionsAction $permissions) {}

    public function execute(Tenant $tenant, User $user, string $id): CommercialOrder
    {
        $actor = $this->permissions->membership($user, $tenant) ?? abort(403);

        return DB::transaction(function () use ($tenant, $actor, $id) {
            $opportunity = Opportunity::query()
                ->where('tenant_id', $tenant->id)
                ->lockForUpdate()
                ->findOrFail($id);

            abort_unless(
                $opportunity->tenant_user_id === $actor->id ||
                in_array($actor->role, [TenantUserRole::Owner, TenantUserRole::Manager], true),
                403
            );

            if ($opportunity->status !== 'open') {
                throw ValidationException::withMessages(['opportunity' => 'Somente oportunidades abertas podem ser convertidas.']);
            }

            if (CommercialOrder::query()->where('opportunity_id', $opportunity->id)->exists()) {
                throw ValidationException::withMessages(['opportunity' => 'Esta oportunidade já possui um pedido.']);
            }

            $items = $opportunity->items()->with('product')->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Adicione linhas ou serviços antes de confirmar a venda.']);
            }

            $client = $opportunity->client()->firstOrFail();
            $first = $items->first();
            if (! $first->product_id || ! $first->operator_id) {
                throw ValidationException::withMessages(['items' => 'O primeiro item precisa ter plano e operadora.']);
            }

            foreach ($items as $item) {
                if (! $item->product_id || ! $item->operator_id || $item->quantity < 1) {
                    throw ValidationException::withMessages(['items' => 'Todos os itens precisam ter plano, operadora e quantidade.']);
                }
            }

            $initialStatus = OrderStatus::query()->where('tenant_id', $tenant->id)
                ->where('is_initial', true)->first();

            if (! $initialStatus) {
                throw ValidationException::withMessages(['order_status' => 'Configure o status inicial dos pedidos antes de confirmar a venda.']);
            }

            $order = CommercialOrder::create([
                'code' => 'PED-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4)),
                'order_status_id' => $initialStatus->id,
                'tenant_user_id' => $opportunity->tenant_user_id,
                'client_id' => $opportunity->client_id,
                'tenant_id' => $tenant->id,
                'opportunity_id' => $opportunity->id,
                'operator_id' => $first->operator_id,
                'product_id' => $first->product_id,
                'order_type' => $opportunity->order_type,
                'customer_name' => $client->company_name,
                'customer_document' => $client->document,
                'monthly_amount' => $opportunity->estimated_monthly_amount,
                'line_quantity' => $items->sum('quantity'),
                'status' => 'DRAFT',
                'sold_on' => now()->toDateString(),
            ]);

            $order->items()->createMany($items->map(fn ($item) => [
                'opportunity_item_id' => $item->id,
                'product_id' => $item->product_id,
                'operator_id' => $item->operator_id,
                'order_type_id' => $item->order_type_id,
                'line_number' => $item->line_number,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'monthly_unit_amount' => $item->monthly_unit_amount,
                'one_time_unit_amount' => $item->one_time_unit_amount,
                'notes' => $item->notes,
            ])->all());

            $opportunity->update(['status' => 'won', 'closed_at' => now()]);

            return $order->load('items');
        });
    }
}
