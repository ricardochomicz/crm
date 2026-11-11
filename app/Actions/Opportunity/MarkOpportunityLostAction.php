<?php

namespace App\Actions\Opportunity;

use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\TenantUserRole;
use App\Models\CommercialOrder;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkOpportunityLostAction
{
    public function __construct(private ResolveTenantPermissionsAction $permissions) {}

    public function execute(Tenant $tenant, User $user, string $id, string $reason): Opportunity
    {
        $actor = $this->permissions->membership($user, $tenant) ?? abort(403);

        return DB::transaction(function () use ($tenant, $actor, $id, $reason) {
            $opportunity = Opportunity::query()->where('tenant_id', $tenant->id)
                ->lockForUpdate()->findOrFail($id);

            abort_unless(
                $opportunity->tenant_user_id === $actor->id ||
                in_array($actor->role, [TenantUserRole::Owner, TenantUserRole::Manager], true),
                403
            );

            if (CommercialOrder::query()->where('tenant_id', $tenant->id)->where('opportunity_id', $opportunity->id)->exists()) {
                throw ValidationException::withMessages(['opportunity' => 'Esta oportunidade possui pedido vinculado. Resolva o pedido pelo fluxo operacional antes de encerrar ou recuperar a oportunidade.']);
            }

            if ($opportunity->status !== 'open') {
                throw ValidationException::withMessages(['opportunity' => 'Somente oportunidades abertas podem ser encerradas.']);
            }

            $opportunity->update([
                'status' => 'lost',
                'lost_reason' => $reason,
                'closed_at' => now(),
            ]);

            return $opportunity->refresh()->load(['client', 'stage', 'orderType']);
        });
    }
}
