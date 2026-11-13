<?php

namespace App\Actions\Tenant;

use App\Enums\SubscriptionStatus;
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

            if (! in_array($subscription->status, [SubscriptionStatus::Trialing, SubscriptionStatus::Active], true)) {
                throw ValidationException::withMessages([
                    'subscription_plan_id' => 'Esta assinatura não permite alteração de plano no momento.',
                ]);
            }

            if ($subscription->subscription_plan_id === $plan->id) {
                throw ValidationException::withMessages(['subscription_plan_id' => 'Este já é o plano atual.']);
            }

            if ($plan->currency !== $subscription->currency
                || $plan->user_limit <= $subscription->user_limit
                || $plan->storage_limit_mb < $subscription->storage_limit_mb
                || (float) $plan->monthly_price <= (float) $subscription->monthly_price) {
                throw ValidationException::withMessages([
                    'subscription_plan_id' => 'Selecione um plano superior na mesma moeda, com maior limite de usuários e valor mensal.',
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
