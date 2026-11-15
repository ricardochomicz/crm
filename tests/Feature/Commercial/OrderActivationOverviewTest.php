<?php

namespace Tests\Feature\Commercial;

use App\Actions\Commercial\OrderActivationOverviewAction;
use App\Models\CommercialOrder;
use App\Models\CommercialOrderItem;
use App\Models\OrderType;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Database\Factories\OperatorFactory;
use Database\Factories\ProductFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OrderActivationOverviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function member(Tenant $tenant): TenantUser
    {
        return TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => User::factory()->create()->id,
            'role' => 'OWNER',
            'status' => 'ACTIVE',
        ]);
    }

    private function order(Tenant $tenant, TenantUser $member): CommercialOrder
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
            'line_quantity' => 1,
            'status' => 'DRAFT',
            'sold_on' => '2026-11-10',
        ]);
    }

    private function line(CommercialOrder $order, OrderType $type, string $date, int $quantity, int $monthly, string $status = 'ACTIVE'): void
    {
        CommercialOrderItem::query()->create([
            'commercial_order_id' => $order->id,
            'product_id' => $order->product_id,
            'operator_id' => $order->operator_id,
            'order_type_id' => $type->id,
            'description' => 'Linha teste',
            'quantity' => $quantity,
            'monthly_unit_amount' => $monthly,
            'status' => $status,
            'activated_at' => $status === 'ACTIVE' ? $date : null,
        ]);
    }

    public function test_aggregates_types_filters_and_compares_equivalent_periods_without_crossing_tenants(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->member($tenant);
        $new = OrderType::query()->create(['name' => 'Novo', 'is_active' => true]);
        $portability = OrderType::query()->create(['name' => 'Portabilidade', 'is_active' => true]);
        $order = $this->order($tenant, $owner);
        $this->line($order, $new, '2026-11-10', 2, 50);
        $this->line($order, $portability, '2026-11-11', 1, 80);
        $this->line($order, $new, '2026-10-10', 1, 40);
        $this->line($order, $new, '2026-11-10', 3, 99, 'PENDING');

        $foreign = Tenant::factory()->create();
        $foreignOrder = $this->order($foreign, $this->member($foreign));
        $this->line($foreignOrder, $new, '2026-11-10', 9, 999);

        $action = app(OrderActivationOverviewAction::class);
        $result = $action->execute($tenant, $owner->user, '2026-11-01', '2026-11-14');
        $this->assertSame(3, $result['total']['lines']);
        $this->assertEquals(180, $result['total']['monthly']);
        $this->assertSame(1, $result['previous_total']['lines']);
        $this->assertSame('2026-10-14', $result['previous_period']['to']);
        $this->assertCount(2, $result['by_type']);

        $filtered = $action->execute($tenant, $owner->user, '2026-11-01', '2026-11-14', [$new->id]);
        $this->assertSame(2, $filtered['total']['lines']);
        $this->assertEquals(100, $filtered['total']['monthly']);
        $this->assertCount(1, $filtered['by_type']);

        $full = $action->execute($tenant, $owner->user, '2026-11-01', '2026-11-30');
        $this->assertSame('2026-10-31', $full['previous_period']['to']);
    }
}
