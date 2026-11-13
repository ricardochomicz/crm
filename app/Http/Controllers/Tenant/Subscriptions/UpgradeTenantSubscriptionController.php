<?php

namespace App\Http\Controllers\Tenant\Subscriptions;

use App\Actions\Tenant\ChangeTenantSubscriptionPlanAction;
use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpgradeTenantSubscriptionController extends Controller
{
    public function __invoke(Request $request, string $tenantId, ChangeTenantSubscriptionPlanAction $change, ResolveTenantPermissionsAction $permissions): JsonResponse
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($tenant instanceof Tenant && $tenant->id === $tenantId, 403);

        $membership = $permissions->membership($request->user(), $tenant);
        abort_unless($membership?->role === TenantUserRole::Owner, 403);

        $data = $request->validate([
            'subscription_plan_id' => ['required', 'uuid', 'exists:subscription_plans,id'],
        ]);

        $change->execute($tenant, $data['subscription_plan_id']);

        return response()->json(['message' => 'Plano atualizado com sucesso.']);
    }
}
