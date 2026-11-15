<?php

namespace Tests\Feature\Commercial;

use App\Actions\Commercial\PendingOrderActivationOverviewAction;
use App\Models\CommercialOrder;
use App\Models\CommercialOrderItem;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Database\Factories\OperatorFactory;
use Database\Factories\ProductFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PendingOrderActivationOverviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function owner(Tenant $tenant): TenantUser
    {
        return TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => User::factory()->create()->id,
            'role' => 'OWNER',
            'status' => 'ACTIVE',
        ]);
    }

    private function order(Tenant $tenant, TenantUser $member, ?string $expected): CommercialOrder
    {
        $operator = OperatorFactory::new()->create();
        $product = ProductFactory::new()->create(['tenant_id' => $tenant->id, 'operator_id' => $operator->id]);

        return CommercialOrder::query()->create([
            'tenant_id' => $tenant->id,
            'tenant_user_id' => $member->id,
            'operator_id' => $operator->id,
            'product_id' => $product->id,
            'order_type' => 'NEW',
            'customer_name' => 'Cliente teste',
            'monthly_amount' => 100,
            'line_quantity' => 3,
            'status' => 'DRAFT',
            'sold_on' => '2026-11-10',
            'expected_activation_at' => $expected,
        ]);
    }

    private function line(CommercialOrder $order, int $quantity, string $status): void
    {
        CommercialOrderItem::query()->create([
            'commercial_order_id' => $order->id,
            'product_id' => $order->product_id,
            'operator_id' => $order->operator_id,
            'description' => 'Linha teste',
            'quantity' => $quantity,
            'monthly_unit_amount' => 50,
            'status' => $status,
            'activated_at' => $status === 'ACTIVE' ? '2026-11-10' : null,
        ]);
    }

    public function test_counts_distinct_orders_pending_lines_overdue_and_isolates_tenants(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->owner($tenant);
        $late = $this->order($tenant, $owner, '2026-11-10');
        $this->line($late, 2, 'PENDING');
        $this->line($late, 1, 'PENDING');
        $this->line($late, 2, 'ACTIVE');

        $onTime = $this->order($tenant, $owner, '2026-11-20');
        $this->line($onTime, 4, 'PENDING');

        $foreign = Tenant::factory()->create();
        $foreignOrder = $this->order($foreign, $this->owner($foreign), '2026-11-01');
        $this->line($foreignOrder, 9, 'PENDING');

        $result = app(PendingOrderActivationOverviewAction::class)->execute($tenant, $owner->user, '2026-11-14');

        $this->assertSame(2, $result['pending']['orders']);
        $this->assertSame(7, $result['pending']['lines']);
        $this->assertEquals(350, $result['pending']['monthly']);
        $this->assertSame(1, $result['overdue']['orders']);
        $this->assertSame(3, $result['overdue']['lines']);
    }
}
