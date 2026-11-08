<?php

namespace App\Actions\Opportunity;

use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\TenantUserRole;
use App\Models\Comment;
use App\Models\CommercialOrder;
use App\Models\Opportunity;
use App\Models\OrderStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResendOpportunityToBackofficeAction
{
    public function __construct(private ResolveTenantPermissionsAction $permissions) {}

    public function execute(Tenant $tenant, User $user, string $id): Opportunity
    {
        $actor = $this->permissions->membership($user, $tenant) ?? abort(403);

        return DB::transaction(function () use ($tenant, $actor, $id): Opportunity {
            $opportunity = Opportunity::query()->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($id);
            abort_unless($opportunity->tenant_user_id === $actor->id ||
                in_array($actor->role, [TenantUserRole::Owner, TenantUserRole::Manager], true), 403);

            $order = CommercialOrder::query()->where('tenant_id', $tenant->id)
                ->where('opportunity_id', $opportunity->id)->lockForUpdate()->firstOrFail();
            $currentStatus = $order->orderStatus;
            if ($opportunity->status !== 'open' || mb_strtolower(trim($currentStatus?->name ?? '')) !== 'para correção') {
                throw ValidationException::withMessages(['opportunity' => 'A oportunidade não está aguardando correção.']);
            }
            if ($order->administratively_completed_at) {
                throw ValidationException::withMessages(['order' => 'Pedido concluído não pode ser reenviado.']);
            }
            $existing = $order->items()->lockForUpdate()->get();
            if ($existing->contains(fn ($item) => $item->status === 'ACTIVE' || $item->activated_at !== null)) {
                throw ValidationException::withMessages(['items' => 'Este pedido possui linhas ativadas. Solicite ao Backoffice a revisão das linhas antes do reenvio.']);
            }
            $items = $opportunity->items()->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'A oportunidade precisa ter linhas para reenviar.']);
            }
            $triage = OrderStatus::query()->where('is_initial', true)->first();
            if (! $triage) {
                throw ValidationException::withMessages(['order_status' => 'Status inicial TRIAGEM não configurado.']);
            }

            $lines = [];
            foreach ($items as $item) {
                for ($i = 0; $i < $item->quantity; $i++) {
                    $lines[] = [
                        'opportunity_item_id' => $i === 0 ? $item->id : null,
                        'product_id' => $item->product_id,
                        'operator_id' => $item->operator_id,
                        'order_type_id' => $item->order_type_id,
                        'line_number' => $item->line_number,
                        'description' => $item->description,
                        'quantity' => 1,
                        'monthly_unit_amount' => $item->monthly_unit_amount,
                        'one_time_unit_amount' => $item->one_time_unit_amount,
                        'notes' => $item->notes,
                        'status' => 'PENDING',
                    ];
                }
            }

            $order->items()->delete();
            $order->items()->createMany($lines);
            $order->update([
                'order_status_id' => $triage->id,
                'operator_id' => $items->first()->operator_id,
                'product_id' => $items->first()->product_id,
                'order_type' => $opportunity->order_type,
                'monthly_amount' => $opportunity->estimated_monthly_amount,
                'line_quantity' => count($lines),
                'activated_at' => null,
            ]);
            $opportunity->update(['status' => 'won', 'closed_at' => now()]);

            $message = sprintf(
                'Correção reenviada ao Backoffice. Pedido %s: %s → %s. %d linha(s). Mensalidade: R$ %s.',
                $order->code, $currentStatus->name, $triage->name, count($lines),
                number_format((float) $order->monthly_amount, 2, ',', '.')
            );
            foreach (['opportunity' => $opportunity->id, 'commercial_order' => $order->id] as $type => $targetId) {
                Comment::create([
                    'tenant_id' => $tenant->id, 'tenant_user_id' => $actor->id,
                    'commentable_type' => $type, 'commentable_id' => $targetId,
                    'content' => $message,
                ]);
            }
            return $opportunity->refresh()->load(['items', 'stage', 'client']);
        });
    }
}
