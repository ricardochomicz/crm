<?php

namespace App\Actions\Commercial;

use App\Enums\TenantUserRole;
use App\Models\CommercialOrder;
use App\Models\Comment;
use App\Models\OrderStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Actions\Tenant\ResolveTenantPermissionsAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateOrderWorkflowAction
{
    public function __construct(private ResolveTenantPermissionsAction $permissions) {}

    public function execute(Tenant $tenant, User $user, string $id, array $data): CommercialOrder
    {
        $actor = $this->permissions->membership($user, $tenant) ?? abort(403);
        abort_unless(in_array($actor->role, [TenantUserRole::Owner, TenantUserRole::Manager], true), 403);

        return DB::transaction(function () use ($tenant, $actor, $id, $data): CommercialOrder {
            $order = CommercialOrder::query()->where('tenant_id', $tenant->id)
                ->lockForUpdate()->findOrFail($id);
            $status = OrderStatus::query()->where('tenant_id', $tenant->id)
                ->findOrFail($data['order_status_id']);

            if ($order->administratively_completed_at) {
                throw ValidationException::withMessages(['order' => 'Pedido concluído. Reabertura administrativa necessária.']);
            }

            if (in_array(mb_strtolower(trim($status->name)), ['ativo', 'parcialmente ativado', 'concluído'], true)) {
                throw ValidationException::withMessages(['order_status_id' => 'Utilize as ações de ativação de linhas ou conclusão administrativa.']);
            }

            if ($order->items()->where('status', 'ACTIVE')->exists()) {
                throw ValidationException::withMessages(['order_status_id' => 'Pedidos com linhas ativadas devem ser movimentados pelo fluxo de ativação.']);
            }

            $previousStatus = $order->orderStatus?->name ?? 'Sem status';
            $previousDate = $order->expected_activation_at?->format('d/m/Y');
            $newDate = isset($data['expected_activation_at']) ? \Carbon\Carbon::parse($data['expected_activation_at'])->format('d/m/Y') : null;

            $order->update([
                'order_status_id' => $data['order_status_id'],
                'expected_activation_at' => $data['expected_activation_at'] ?? null,
            ]);

            $changes = [];
            if ($order->wasChanged('order_status_id')) {
                $changes[] = sprintf('Status do pedido: %s → %s.', $previousStatus, $status->name);
            }
            if ($order->wasChanged('expected_activation_at')) {
                $changes[] = sprintf('Data de ativação informada: %s → %s.', $previousDate ?? 'Não informada', $newDate ?? 'Não informada');
            }
            if ($changes) {
                Comment::create([
                    'tenant_id' => $tenant->id,
                    'tenant_user_id' => $actor->id,
                    'commentable_type' => 'commercial_order',
                    'commentable_id' => $order->id,
                    'content' => implode(' ', $changes),
                ]);
            }

            return $order->load(['orderStatus:id,name', 'items']);
        });
    }
}
