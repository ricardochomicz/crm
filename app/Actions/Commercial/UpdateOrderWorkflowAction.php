<?php

namespace App\Actions\Commercial;

use App\Enums\TenantUserRole;
use App\Models\CommercialOrder;
use App\Models\OrderStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Actions\Tenant\ResolveTenantPermissionsAction;
use Illuminate\Support\Facades\DB;

class UpdateOrderWorkflowAction
{
    public function __construct(private ResolveTenantPermissionsAction $permissions) {}

    public function execute(Tenant $tenant, User $user, string $id, array $data): CommercialOrder
    {
        $actor = $this->permissions->membership($user, $tenant) ?? abort(403);
        abort_unless(in_array($actor->role, [TenantUserRole::Owner, TenantUserRole::Manager], true), 403);

        return DB::transaction(function () use ($tenant, $id, $data): CommercialOrder {
            $order = CommercialOrder::query()->where('tenant_id', $tenant->id)
                ->lockForUpdate()->findOrFail($id);
            OrderStatus::query()->where('tenant_id', $tenant->id)
                ->findOrFail($data['order_status_id']);

            $order->update([
                'order_status_id' => $data['order_status_id'],
                'expected_activation_at' => $data['expected_activation_at'] ?? null,
            ]);

            return $order->load(['orderStatus:id,name', 'items']);
        });
    }
}
