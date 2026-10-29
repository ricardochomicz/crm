<?php

namespace Tests\Feature\Actions\Tenant;

use App\Actions\Tenant\CreateTenantAction;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Enums\TenantType;
use App\Enums\TenantUserRole;
use App\Enums\TenantUserStatus;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class CreateTenantActionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_tenant_owner_and_trial_subscription(): void
    {
        $this->travelTo(Carbon::parse('2026-10-29 12:00:00'));

        $owner = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create([
            'monthly_price' => '129.00',
            'user_limit' => 3,
            'storage_limit_mb' => 2048,
        ]);

        $tenant = (new CreateTenantAction())->handle(
            $owner,
            $plan->id,
            $this->tenantData(),
        );

        $tenant->refresh();
        $member = $tenant->users()->sole();
        $subscription = $tenant->subscription;

        $this->assertTrue(Str::isUuid($tenant->id));
        $this->assertSame('Consultoria de teste', $tenant->name);
        $this->assertSame(TenantType::Individual, $tenant->type);
        $this->assertSame(TenantStatus::Active, $tenant->status);
        $this->assertSame('America/Sao_Paulo', $tenant->timezone);

        $this->assertSame($owner->id, $member->id);
        $this->assertSame(
            TenantUserRole::Owner,
            $member->membership->role,
        );
        $this->assertSame(
            TenantUserStatus::Active,
            $member->membership->status,
        );

        $this->assertSame($plan->id, $subscription->subscription_plan_id);
        $this->assertSame(
            SubscriptionStatus::Trialing,
            $subscription->status,
        );
        $this->assertSame('129.00', $subscription->monthly_price);
        $this->assertSame('BRL', $subscription->currency);
        $this->assertSame(3, $subscription->user_limit);
        $this->assertSame(2048, $subscription->storage_limit_mb);
        $this->assertSame(
            '2026-10-29 12:00:00',
            $subscription->started_at->format('Y-m-d H:i:s'),
        );
        $this->assertSame(
            '2026-11-12 12:00:00',
            $subscription->trial_ends_at->format('Y-m-d H:i:s'),
        );
        $this->assertFalse($subscription->cancel_at_period_end);

        $this->assertDatabaseCount('tenants', 1);
        $this->assertDatabaseCount('tenant_users', 1);
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_rejects_inactive_plan_without_creating_records(): void
    {
        $owner = User::factory()->create();
        $plan = SubscriptionPlan::factory()->inactive()->create();

        try {
            (new CreateTenantAction())->handle(
                $owner,
                $plan->id,
                $this->tenantData(),
            );

            $this->fail('Era esperado rejeitar o plano inativo.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'subscription_plan_id' => [
                    'Este plano não está disponível para contratação.',
                ],
            ], $exception->errors());
        }

        $this->assertNoTenantRecords();
        $this->assertModelExists($owner);
    }

    public function test_rejects_missing_plan_without_creating_records(): void
    {
        $owner = User::factory()->create();

        try {
            (new CreateTenantAction())->handle(
                $owner,
                (string) Str::uuid(),
                $this->tenantData(),
            );

            $this->fail('Era esperado rejeitar o plano inexistente.');
        } catch (ModelNotFoundException $exception) {
            $this->assertSame(
                SubscriptionPlan::class,
                $exception->getModel(),
            );
        }

        $this->assertNoTenantRecords();
    }

    public function test_rolls_back_tenant_and_membership_if_subscription_fails(): void
    {
        $owner = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();

        $originalDispatcher = Subscription::getEventDispatcher();
        Subscription::setEventDispatcher(clone $originalDispatcher);

        try {
            Subscription::creating(function (Subscription $subscription): void {
                throw new RuntimeException('Falha simulada na assinatura.');
            });

            try {
                (new CreateTenantAction())->handle(
                    $owner,
                    $plan->id,
                    $this->tenantData(),
                );

                $this->fail('Era esperado interromper a criação da assinatura.');
            } catch (RuntimeException $exception) {
                $this->assertSame(
                    'Falha simulada na assinatura.',
                    $exception->getMessage(),
                );
            }
        } finally {
            Subscription::setEventDispatcher($originalDispatcher);
        }

        $this->assertNoTenantRecords();
        $this->assertModelExists($owner);
        $this->assertModelExists($plan);
    }

    /**
     * @return array{
     *     name: string,
     *     type: TenantType,
     *     document: string,
     *     email: string
     * }
     */
    private function tenantData(): array
    {
        return [
            'name' => 'Consultoria de teste',
            'type' => TenantType::Individual,
            'document' => '52998224725',
            'email' => 'contato@example.test',
        ];
    }

    private function assertNoTenantRecords(): void
    {
        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('tenant_users', 0);
        $this->assertDatabaseCount('subscriptions', 0);
    }
}
