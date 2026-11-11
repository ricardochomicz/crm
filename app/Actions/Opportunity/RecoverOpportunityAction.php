<?php

namespace App\Actions\Opportunity;

use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\TenantUserRole;
use App\Models\CommercialOrder;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecoverOpportunityAction
{
    public function __construct(private ResolveTenantPermissionsAction $permissions) {}

    public function execute(Tenant $tenant, User $user, string $id): Opportunity
    {
        $actor = $this->permissions->membership($user, $tenant) ?? abort(403);

        return DB::transaction(function () use ($tenant, $actor, $id) {
            $original = Opportunity::query()->where('tenant_id', $tenant->id)
                ->lockForUpdate()->findOrFail($id);

            abort_unless(
                $original->tenant_user_id === $actor->id ||
                in_array($actor->role, [TenantUserRole::Owner, TenantUserRole::Manager], true),
                403
            );

            if (CommercialOrder::query()->where('tenant_id', $tenant->id)->where('opportunity_id', $original->id)->exists()) {
                throw ValidationException::withMessages(['opportunity' => 'Esta oportunidade possui pedido vinculado. Resolva o pedido pelo fluxo operacional antes de encerrar ou recuperar a oportunidade.']);
            }

            if ($original->status !== 'lost') {
                throw ValidationException::withMessages(['opportunity' => 'Somente oportunidades perdidas podem ser recuperadas.']);
            }

            $original->load('items');
            if ($original->items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'A oportunidade original não possui itens para recuperar.']);
            }

            $copy = $original->replicate([
                'code', 'status', 'closed_at', 'lost_reason', 'expected_close_at',
                'funnel_stage_id', 'parent_opportunity_id',
            ]);
            $copy->code = 'OP-'.now()->format('YmdHis').'-'.strtoupper(Str::random(6));
            $copy->status = 'open';
            $copy->closed_at = null;
            $copy->lost_reason = null;
            $copy->expected_close_at = null;
            $copy->funnel_stage_id = null;
            $copy->parent_opportunity_id = $original->id;
            $copy->title = 'Retomada - '.$original->title;
            $copy->save();

            foreach ($original->items as $item) {
                $copy->items()->create($item->only([
                    'product_id', 'operator_id', 'order_type_id', 'operation_type',
                    'line_number', 'description', 'quantity', 'monthly_unit_amount',
                    'one_time_unit_amount', 'notes',
                ]));
            }

            return $copy->load(['client', 'stage', 'orderType', 'items.orderType']);
        });
    }
}
