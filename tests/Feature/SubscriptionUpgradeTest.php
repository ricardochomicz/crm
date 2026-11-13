<?php

namespace Tests\Feature;

use App\Actions\Tenant\ChangeTenantSubscriptionPlanAction;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SubscriptionUpgradeTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_upgrade_changes_contract_limits_without_replacing_subscription(): void
    {
        $tenant = Tenant::factory()->create();
        $current = SubscriptionPlan::factory()->create([
            'monthly_price' => '59.00', 'user_limit' => 1, 'storage_limit_mb' => 1024, 'currency' => 'BRL',
        ]);
        $upgrade = SubscriptionPlan::factory()->create([
            'monthly_price' => '199.00', 'user_limit' => 5, 'storage_limit_mb' => 5120,
            'currency' => 'BRL', 'is_active' => true,
        ]);
        $subscription = Subscription::factory()->for($tenant)->forPlan($current)->create([
            'status' => SubscriptionStatus::Active,
        ]);
        $id = $subscription->id;

        app(ChangeTenantSubscriptionPlanAction::class)->execute($tenant, $upgrade->id);

        $subscription->refresh();
        $this->assertSame($id, $subscription->id);
        $this->assertSame($upgrade->id, $subscription->subscription_plan_id);
        $this->assertSame(5, $subscription->user_limit);
        $this->assertSame(5120, $subscription->storage_limit_mb);
        $this->assertSame('199.00', $subscription->monthly_price);
    }

    public function test_downgrade_is_rejected_without_changing_subscription(): void
    {
        $tenant = Tenant::factory()->create();
        $current = SubscriptionPlan::factory()->create([
            'monthly_price' => '199.00', 'user_limit' => 5, 'storage_limit_mb' => 5120,
        ]);
        $lower = SubscriptionPlan::factory()->create([
            'monthly_price' => '59.00', 'user_limit' => 1, 'storage_limit_mb' => 1024,
            'is_active' => true,
        ]);
        $subscription = Subscription::factory()->for($tenant)->forPlan($current)->create([
            'status' => SubscriptionStatus::Active,
        ]);

        try {
            app(ChangeTenantSubscriptionPlanAction::class)->execute($tenant, $lower->id);
            $this->fail('O downgrade deveria ser bloqueado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('subscription_plan_id', $exception->errors());
        }

        $this->assertSame($current->id, $subscription->fresh()->subscription_plan_id);
    }
}
