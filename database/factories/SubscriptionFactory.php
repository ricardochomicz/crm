<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'subscription_plan_id' => SubscriptionPlan::factory(),
            'status' => SubscriptionStatus::Pending,
            'monthly_price' => '59.00',
            'currency' => 'BRL',
            'user_limit' => 1,
            'storage_limit_mb' => 1024,
            'started_at' => null,
            'trial_ends_at' => null,
            'current_period_starts_at' => null,
            'current_period_ends_at' => null,
            'grace_ends_at' => null,
            'cancel_at_period_end' => false,
            'cancelled_at' => null,
        ];
    }

    public function forPlan(SubscriptionPlan $plan): static
    {
        return $this->for($plan, 'plan')->state(
            fn(array $attributes): array => [
                'monthly_price' => $plan->monthly_price,
                'currency' => $plan->currency,
                'user_limit' => $plan->user_limit,
                'storage_limit_mb' => $plan->storage_limit_mb,
            ],
        );
    }

    public function trialing(): static
    {
        return $this->state(fn(array $attributes): array => [
            'status' => SubscriptionStatus::Trialing,
            'started_at' => now(),
            'trial_ends_at' => now()->addDays(14),
        ]);
    }
}
