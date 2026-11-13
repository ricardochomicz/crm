<?php

namespace App\Actions\Tenant;

use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeTenantSubscriptionPlanAction
{
    public function execute(Tenant $tenant, string $planId): void
    {
        DB::transaction(function () use ($tenant, $planId): void {
            $subscription = $tenant->subscription()->lockForUpdate()->firstOrFail();
            $plan = SubscriptionPlan::query()->where('is_active', true)->findOrFail($planId);

            if ($subscription->subscription_plan_id === $plan->id) {
                throw ValidationException::withMessages(['subscription_plan_id' => 'Este já é o plano atual.']);
            }

            if ($plan->user_limit <= $subscription->user_limit
                || $plan->storage_limit_mb < $subscription->storage_limit_mb
                || (float) $plan->monthly_price < (float) $subscription->monthly_price) {
                throw ValidationException::withMessages([
                    'subscription_plan_id' => 'Selecione um plano superior, sem redução de capacidade ou valor.',
                ]);
            }

            $subscription->update([
                'subscription_plan_id' => $plan->id,
                'monthly_price' => $plan->monthly_price,
                'currency' => $plan->currency,
                'user_limit' => $plan->user_limit,
                'storage_limit_mb' => $plan->storage_limit_mb,
            ]);
        });
    }
}
