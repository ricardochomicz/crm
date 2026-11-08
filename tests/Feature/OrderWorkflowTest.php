<?php

namespace Tests\Feature;

use App\Models\CommercialOrder;
use App\Models\OrderStatus;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Database\Factories\ProductFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OrderWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_operational_changes_preserve_active_and_pending_lines(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $tenant->users()->attach($user, ['role' => 'OWNER', 'status' => 'ACTIVE']);
        Subscription::factory()->for($tenant)->trialing()->create();
        $product = ProductFactory::new()->create(['tenant_id' => $tenant->id]);
        $partial = OrderStatus::create(['name' => 'PARCIALMENTE ATIVADO', 'is_initial' => false]);
        $returned = OrderStatus::create(['name' => 'DEVOLUÇÃO', 'is_initial' => false]);
        $order = CommercialOrder::create([
            'tenant_id' => $tenant->id, 'operator_id' => $product->operator_id,
            'product_id' => $product->id, 'order_type' => 'PORTABILITY',
            'customer_name' => 'Empresa', 'monthly_amount' => '99.80',
            'line_quantity' => 2, 'sold_on' => '2026-10-20',
            'order_status_id' => $partial->id,
        ]);
        $active = $order->items()->create([
            'product_id' => $product->id, 'description' => 'Linha ativa', 'quantity' => 1,
            'monthly_unit_amount' => '49.90', 'status' => 'ACTIVE', 'activated_at' => '2026-10-31',
        ]);
        $pending = $order->items()->create([
            'product_id' => $product->id, 'description' => 'Linha pendente', 'quantity' => 1,
            'monthly_unit_amount' => '49.90', 'status' => 'PENDING',
        ]);

        $this->actingAs($user)->patchJson("/api/v1/tenants/{$tenant->id}/commercial-orders/{$order->id}/workflow", [
            'order_status_id' => $returned->id, 'expected_activation_at' => '2026-11-10',
        ])->assertOk()->assertJsonPath('data.order_status', 'DEVOLUÇÃO');

        $this->assertDatabaseHas('commercial_orders', [
            'id' => $order->id, 'order_status_id' => $returned->id,
            'expected_activation_at' => '2026-11-10', 'administratively_completed_at' => null,
        ]);
        $this->assertDatabaseHas('commercial_order_items', [
            'id' => $active->id, 'status' => 'ACTIVE', 'activated_at' => '2026-10-31',
        ]);
        $this->assertDatabaseHas('commercial_order_items', [
            'id' => $pending->id, 'status' => 'PENDING', 'activated_at' => null,
        ]);
        $this->assertDatabaseHas('comments', [
            'commentable_id' => $order->id,
            'content' => 'Status do pedido: PARCIALMENTE ATIVADO → DEVOLUÇÃO. Previsão de ativação: Não informada → 10/11/2026.',
        ]);
    }
}
