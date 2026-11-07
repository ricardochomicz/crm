<?php

namespace App\Actions\Commercial;

use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\TenantUserRole;
use App\Models\Comment;
use App\Models\CommercialOrder;
use App\Models\OrderStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteOrderAction
{
    public function __construct(private ResolveTenantPermissionsAction $permissions) {}

    public function execute(Tenant $tenant, User $user, string $orderId): CommercialOrder
    {
        $actor = $this->permissions->membership($user, $tenant) ?? abort(403);
        abort_unless(in_array($actor->role, [TenantUserRole::Owner, TenantUserRole::Manager], true), 403);

        return DB::transaction(function () use ($tenant, $actor, $orderId): CommercialOrder {
            $order = CommercialOrder::query()->where('tenant_id', $tenant->id)
                ->lockForUpdate()->findOrFail($orderId);

            if ($order->administratively_completed_at) {
                throw ValidationException::withMessages(['order' => 'Este pedido já está concluído.']);
            }

            $items = $order->items()->lockForUpdate()->get();

            if ($items->isEmpty() || $items->contains(fn ($item) => $item->status !== 'ACTIVE' || ! $item->activated_at)) {
                throw ValidationException::withMessages(['items' => 'Todas as linhas precisam estar ativas e possuir data de ativação.']);
            }

            $status = OrderStatus::query()->where('name', 'CONCLUIDO')->first();

            if (! $status) {
                throw ValidationException::withMessages(['order_status' => 'Cadastre o status Concluído antes de finalizar pedidos.']);
            }

            $previousStatus = $order->orderStatus?->name ?? 'Sem status';
            $order->update([
                'order_status_id' => $status->id,
                'administratively_completed_at' => now(),
            ]);

            Comment::create([
                'tenant_id' => $tenant->id,
                'tenant_user_id' => $actor->id,
                'commentable_type' => 'commercial_order',
                'commentable_id' => $order->id,
                'content' => sprintf('Status do pedido: %s → Concluído. Pedido concluído administrativamente; todas as linhas estão ativadas.', $previousStatus),
            ]);

            return $order->load(['orderStatus:id,name', 'items', 'consultant.user:id,name']);
        });
    }
}
