<?php

namespace App\Actions\Commercial;

use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\OrderStatusCode;
use App\Events\CommercialOrdersChanged;
use App\Models\Comment;
use App\Models\CommercialOrder;
use App\Models\OpportunityFunnelStage;
use App\Models\OrderStatus;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateOrderWorkflowAction
{
    public function __construct(private ResolveTenantPermissionsAction $permissions) {}

    public function execute(Tenant $tenant, User $user, string $id, array $data): CommercialOrder
    {
        $actor = $this->permissions->membership($user, $tenant) ?? abort(403);
        abort_unless($user->can('manageOrders', $tenant), 403);

        return DB::transaction(function () use ($tenant, $actor, $id, $data): CommercialOrder {
            $order = CommercialOrder::query()->where('tenant_id', $tenant->id)
                ->lockForUpdate()->findOrFail($id);
            $status = OrderStatus::query()->findOrFail($data['order_status_id']);

            if ($order->administratively_completed_at) {
                throw ValidationException::withMessages(['order' => 'Pedido concluído. Reabertura administrativa necessária.']);
            }

            if ($status->code?->requiresActivation()) {
                throw ValidationException::withMessages(['order_status_id' => 'Utilize as ações de ativação de linhas ou conclusão administrativa.']);
            }

            $previousStatus = $order->orderStatus?->name ?? 'Sem status';
            $previousDate = $order->expected_activation_at ? Carbon::parse($order->expected_activation_at)->format('d/m/Y') : null;
            $expectedActivationAt = array_key_exists('expected_activation_at', $data) ? $data['expected_activation_at'] : $order->expected_activation_at;
            $newDate = $expectedActivationAt !== null ? Carbon::parse($expectedActivationAt)->format('d/m/Y') : null;

            $returnForCorrection = $status->code === OrderStatusCode::Correction;
            if ($returnForCorrection && $order->order_status_id !== $status->id && blank($data['correction_reason'] ?? null)) {
                throw ValidationException::withMessages(['correction_reason' => 'Informe o motivo da devolução ao consultor.']);
            }
            if ($returnForCorrection && ! $order->opportunity_id) {
                throw ValidationException::withMessages(['order_status_id' => 'Pedido sem oportunidade vinculada para devolução.']);
            }

            $order->update([
                'order_status_id' => $data['order_status_id'],
                'expected_activation_at' => $expectedActivationAt,
            ]);

            if ($returnForCorrection && $order->wasChanged('order_status_id')) {
                $opportunity = $order->opportunity()->lockForUpdate()->firstOrFail();
                $correctionStage = OpportunityFunnelStage::query()
                    ->where('tenant_id', $tenant->id)->where('name', 'Para Correção')->first();
                if (! $correctionStage) {
                    $nextPosition = ((int) OpportunityFunnelStage::query()
                        ->where('tenant_id', $tenant->id)->max('position')) + 1;
                    $correctionStage = OpportunityFunnelStage::query()->create([
                        'tenant_id' => $tenant->id, 'name' => 'Para Correção',
                        'position' => $nextPosition, 'is_active' => true,
                    ]);
                }
                $opportunity->update(['status' => 'open', 'closed_at' => null, 'funnel_stage_id' => $correctionStage->id]);
            }

            $changes = [];
            if ($returnForCorrection && $order->wasChanged('order_status_id')) {
                $changes[] = 'Motivo da devolução ao consultor: '.trim($data['correction_reason']);
            }
            if ($returnForCorrection && $order->wasChanged('order_status_id')) {
                Comment::create([
                    'tenant_id' => $tenant->id,
                    'tenant_user_id' => $actor->id,
                    'commentable_type' => 'opportunity',
                    'commentable_id' => $order->opportunity_id,
                    'content' => sprintf(
                        'Pedido %s devolvido para correção pelo Backoffice. Status: %s → %s. Motivo: %s',
                        $order->code ?: substr($order->id, 0, 8),
                        $previousStatus,
                        $status->name,
                        trim($data['correction_reason'])
                    ),
                ]);
            }
            if ($order->wasChanged('order_status_id')) {
                $changes[] = sprintf('Status do pedido: %s → %s.', $previousStatus, $status->name);
            }
            if ($order->wasChanged('expected_activation_at')) {
                $changes[] = sprintf('Previsão de ativação: %s → %s.', $previousDate ?? 'Não informada', $newDate ?? 'Não informada');
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

            if ($changes) {
                DB::afterCommit(fn () => CommercialOrdersChanged::dispatch($tenant->id));
            }

            return $order->load(['orderStatus:id,name', 'items']);
        });
    }
}
