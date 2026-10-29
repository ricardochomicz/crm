<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_subscription_related_to_tenant_and_plan(): void
    {
        $tenant = Tenant::factory()->create();
        $plan = SubscriptionPlan::factory()->create();

        $subscription = Subscription::factory()
            ->for($tenant)
            ->forPlan($plan)
            ->create();

        $subscription->refresh();

        $this->assertTrue(Str::isUuid($subscription->id));
        $this->assertSame(
            SubscriptionStatus::Pending,
            $subscription->status,
        );
        $this->assertFalse($subscription->cancel_at_period_end);
        $this->assertSame($tenant->id, $subscription->tenant->id);
        $this->assertSame($plan->id, $subscription->plan->id);
        $this->assertSame(
            $subscription->id,
            $tenant->subscription->id,
        );
        $this->assertSame(
            $subscription->id,
            $plan->subscriptions()->sole()->id,
        );
    }

    public function test_preserves_contracted_terms_when_plan_changes(): void
    {
        $plan = SubscriptionPlan::factory()->create([
            'monthly_price' => '129.00',
            'currency' => 'BRL',
            'user_limit' => 3,
            'storage_limit_mb' => 2048,
        ]);

        $subscription = Subscription::factory()
            ->forPlan($plan)
            ->create();

        $plan->update([
            'monthly_price' => '149.00',
            'user_limit' => 5,
            'storage_limit_mb' => 4096,
            'is_active' => false,
        ]);

        $subscription->refresh();

        $this->assertSame('129.00', $subscription->monthly_price);
        $this->assertSame('BRL', $subscription->currency);
        $this->assertSame(3, $subscription->user_limit);
        $this->assertSame(2048, $subscription->storage_limit_mb);
        $this->assertSame(
            SubscriptionStatus::Pending,
            $subscription->status,
        );
    }

    public function test_rejects_second_subscription_for_same_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $plan = SubscriptionPlan::factory()->create();

        Subscription::factory()
            ->for($tenant)
            ->forPlan($plan)
            ->create();

        $this->expectException(UniqueConstraintViolationException::class);

        Subscription::factory()
            ->for($tenant)
            ->forPlan($plan)
            ->create();
    }

    public function test_allows_two_tenants_to_subscribe_to_same_plan(): void
    {
        $firstTenant = Tenant::factory()->create();
        $secondTenant = Tenant::factory()->create();
        $plan = SubscriptionPlan::factory()->create();

        $firstSubscription = Subscription::factory()
            ->for($firstTenant)
            ->forPlan($plan)
            ->create();

        $secondSubscription = Subscription::factory()
            ->for($secondTenant)
            ->forPlan($plan)
            ->create();

        $this->assertEqualsCanonicalizing(
            [$firstSubscription->id, $secondSubscription->id],
            $plan->subscriptions()->pluck('id')->all(),
        );
        $this->assertSame(
            $firstSubscription->id,
            $firstTenant->subscription->id,
        );
        $this->assertSame(
            $secondSubscription->id,
            $secondTenant->subscription->id,
        );
    }
}
