<?php

namespace App\Actions\Tenant;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Enums\TenantType;
use App\Enums\TenantUserRole;
use App\Enums\TenantUserStatus;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateTenantAction
{
    /**
     * @param array{
     *     name: string,
     *     type: TenantType,
     *     document: string,
     *     email: string,
     *     trade_name?: string|null,
     *     phone?: string|null,
     *     timezone?: string
     * } $data
     */
    public function handle(
        User $owner,
        string $planId,
        array $data,
    ): Tenant {
        return DB::transaction(function () use ($owner, $planId, $data): Tenant {

            $owner = User::query()
                ->lockForUpdate()
                ->findOrFail($owner->getKey());

            $alreadyOwnsTenant = $owner->tenants()
                ->wherePivot('role', TenantUserRole::Owner->value)
                ->exists();

            if ($alreadyOwnsTenant) {
                throw ValidationException::withMessages([
                    'tenant' => 'Você já possui uma empresa cadastrada.',
                ]);
            }

            $plan = SubscriptionPlan::query()
                ->sharedLock()
                ->findOrFail($planId);

            if (! $plan->is_active) {
                throw ValidationException::withMessages([
                    'subscription_plan_id' => 'Este plano não está disponível para contratação.',
                ]);
            }

            $startedAt = now();

            $tenant = Tenant::create([
                'name' => $data['name'],
                'type' => $data['type'],
                'document' => $data['document'],
                'trade_name' => $data['trade_name'] ?? null,
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'timezone' => $data['timezone'] ?? 'America/Sao_Paulo',
                'status' => TenantStatus::Active,
            ]);

            $tenant->users()->attach($owner->getKey(), [
                'role' => TenantUserRole::Owner->value,
                'status' => TenantUserStatus::Active->value,
                'joined_at' => $startedAt,
            ]);

            $tenant->subscription()->create([
                'subscription_plan_id' => $plan->id,
                'status' => SubscriptionStatus::Trialing,
                'monthly_price' => $plan->monthly_price,
                'currency' => $plan->currency,
                'user_limit' => $plan->user_limit,
                'storage_limit_mb' => $plan->storage_limit_mb,
                'started_at' => $startedAt,
                'trial_ends_at' => $startedAt->copy()->addDays(14),
                'cancel_at_period_end' => false,
            ]);

            return $tenant->load('subscription');
        }, attempts: 5);
    }
}
