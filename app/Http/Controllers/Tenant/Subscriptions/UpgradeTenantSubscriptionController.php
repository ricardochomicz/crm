<?php

namespace App\Http\Controllers\Tenant\Subscriptions;

use App\Actions\Tenant\ChangeTenantSubscriptionPlanAction;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpgradeTenantSubscriptionController extends Controller
{
    public function __invoke(Request $request, string $tenantId, ChangeTenantSubscriptionPlanAction $change): JsonResponse
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($tenant instanceof Tenant && $tenant->id === $tenantId, 403);

        $membership = $tenant->users()
            ->whereKey($request->user()->id)
            ->first();

        abort_unless($membership && $membership->pivot->role->value === 'OWNER', 403);

        $data = $request->validate([
            'subscription_plan_id' => ['required', 'uuid', 'exists:subscription_plans,id'],
        ]);

        $change->execute($tenant, $data['subscription_plan_id']);

        return response()->json(['message' => 'Plano atualizado com sucesso.']);
    }
}
