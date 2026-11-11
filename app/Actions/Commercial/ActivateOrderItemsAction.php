<?php

namespace App\Actions\Commercial;

use App\Actions\Commission\CaptureOrderCommissionsAction;
use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\OrderStatusCode;
use App\Models\Comment;
use App\Models\CommercialOrder;
use App\Models\OrderStatus;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivateOrderItemsAction
{
    public function __construct(
        private ResolveTenantPermissionsAction $permissions,
        private CaptureOrderCommissionsAction $commissions,
    ) {}

    public function execute(Tenant $tenant, User $user, string $orderId, array $data): CommercialOrder
    {
        $actor = $this->permissions->membership($user, $tenant) ?? abort(403);
        abort_unless($user->can('manageOrders', $tenant), 403);

        return DB::transaction(function () use ($tenant, $actor, $orderId, $data): CommercialOrder {
            $order = CommercialOrder::query()->where('tenant_id', $tenant->id)
                ->lockForUpdate()->findOrFail($orderId);

            if ($order->administratively_completed_at) {
                throw ValidationException::withMessages(['order' => 'Reabra o pedido antes de movimentar linhas.']);
            }

            $items = $order->items()->lockForUpdate()->get();
            $selected = $items->whereIn('id', $data['item_ids']);

            if ($items->isEmpty() || $selected->count() !== count($data['item_ids'])) {
                throw ValidationException::withMessages(['item_ids' => 'Uma ou mais linhas não pertencem ao pedido.']);
            }

            $invalid = $selected->first(fn ($item) => $item->status !== 'PENDING' || $item->activated_at !== null);
            if ($invalid) {
                $line = $invalid->line_number ?: ($invalid->description ?: 'sem número');
                throw ValidationException::withMessages([
                    'item_ids' => "A linha {$line} não está mais pendente ou já possui data de ativação. Atualize o pedido e selecione somente linhas pendentes.",
                ]);
            }

            if ($selected->contains(fn ($item) => $item->quantity !== 1)) {
                throw ValidationException::withMessages(['item_ids' => 'Itens com quantidade maior que um precisam ser individualizados antes da ativação parcial.']);
            }

            $previousStatus = $order->orderStatus?->name ?? 'Sem status';
            $remaining = $items->filter(fn ($item) => $item->status === 'PENDING')->count() - $selected->count();

            foreach ($selected as $item) {
                $item->update(['status' => 'ACTIVE', 'activated_at' => $data['activated_at']]);
            }

            $this->commissions->activate($order, $selected->pluck('id')->values()->all(), $data['activated_at']);

            $code = $remaining === 0 ? OrderStatusCode::Completed : OrderStatusCode::PartiallyActivated;
            $status = OrderStatus::query()->where('code', $code->value)->first();

            if (! $status) {
                throw ValidationException::withMessages(['order_status' => 'Configure os status de conclusão e ativação parcial antes de ativar linhas.']);
            }

            $order->update([
                'order_status_id' => $status->id,
                'activated_at' => $remaining === 0 ? $data['activated_at'] : null,
                'administratively_completed_at' => $remaining === 0 ? now() : null,
            ]);

            Comment::create([
                'tenant_id' => $tenant->id,
                'tenant_user_id' => $actor->id,
                'commentable_type' => 'commercial_order',
                'commentable_id' => $order->id,
                'content' => sprintf(
                    'Status do pedido: %s → %s. %d linha(s) ativada(s) em %s. Linhas: %s.',
                    $previousStatus,
                    $status->name,
                    $selected->count(),
                    Carbon::parse($data['activated_at'])->format('d/m/Y'),
                    $selected->map(fn ($item) => $item->line_number ?: ($item->description ?: 'Linha sem número'))->implode(', ')
                ),
            ]);

            return $order->load(['orderStatus:id,name', 'items', 'consultant.user:id,name']);
        });
    }
}
