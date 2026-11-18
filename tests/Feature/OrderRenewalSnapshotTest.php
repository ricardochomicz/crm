<?php

namespace Tests\Feature;

use App\Actions\Commercial\CaptureOrderRenewalSnapshotAction;
use App\Actions\Commercial\CompleteOrderAction;
use App\Actions\Commercial\ActivateOrderItemsAction;
use App\Actions\Client\ListClientContractRenewalsAction;
use App\Enums\OrderStatusCode;
use App\Models\OrderStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Client;
use App\Models\ClientOperatorClassification;
use App\Models\ClassificationRenewalRule;
use App\Models\CommercialOrder;
use App\Models\Operator;
use App\Models\OperatorClassification;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;
use Database\Factories\ProductFactory;

class OrderRenewalSnapshotTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_legacy_order_without_snapshot_uses_current_rule_and_is_tenant_scoped(): void
    {
        CarbonImmutable::setTestNow('2026-11-18 12:00:00');

        try {
            $tenant = Tenant::factory()->create();
            $otherTenant = Tenant::factory()->create();
            $operator = Operator::factory()->create();
            $product = ProductFactory::new()->create([
                'tenant_id' => $tenant->id, 'operator_id' => $operator->id,
            ]);
            $client = Client::query()->create([
                'tenant_id' => $tenant->id, 'person_type' => 'PJ',
                'document' => '12345678000195', 'company_name' => 'Cliente legado',
                'is_active' => true,
            ]);
            $otherClient = Client::query()->create([
                'tenant_id' => $otherTenant->id, 'person_type' => 'PJ',
                'document' => '12345678000195', 'company_name' => 'Outra empresa',
                'is_active' => true,
            ]);
            $classification = OperatorClassification::query()->create([
                'tenant_id' => $tenant->id, 'operator_id' => $operator->id,
                'code' => 'LEGACY-TEST', 'name' => 'Legado', 'is_active' => true,
            ]);
            ClientOperatorClassification::query()->create([
                'tenant_id' => $tenant->id, 'client_id' => $client->id,
                'operator_id' => $operator->id, 'classification_id' => $classification->id,
            ]);
            $rule = ClassificationRenewalRule::query()->create([
                'tenant_id' => $tenant->id, 'classification_id' => $classification->id,
                'minimum_months' => 18, 'valid_from' => '2026-01-01',
                'is_active' => true,
            ]);
            $order = CommercialOrder::query()->create([
                'tenant_id' => $tenant->id, 'client_id' => null,
                'customer_document' => $client->document,
                'operator_id' => $operator->id, 'product_id' => $product->id,
                'code' => 'LEGACY-RENEWAL', 'order_type' => 'PORTABILITY',
                'customer_name' => 'Cliente legado', 'monthly_amount' => 100,
                'line_quantity' => 1, 'sold_on' => '2026-10-01',
                'administratively_completed_at' => '2026-11-01 12:00:00',
            ]);
            $order->items()->create([
                'product_id' => $product->id, 'description' => 'Linha',
                'quantity' => 1, 'monthly_unit_amount' => 100,
                'status' => 'ACTIVE', 'activated_at' => '2026-10-31',
            ]);

            $this->assertNull($order->fresh()->renewal_snapshot_at);
            $renewals = app(ListClientContractRenewalsAction::class);
            $original = $renewals->execute($client)->first();
            $this->assertSame($order->id, $original['order_id']);
            $this->assertSame(18, $original['renewal_months']);
            $this->assertSame('2028-04-30', $original['renewal_date']);
            $this->assertTrue($renewals->execute($otherClient)->isEmpty());

            $rule->update(['minimum_months' => 24]);
            $updated = $renewals->execute($client)->first();
            $this->assertSame(24, $updated['renewal_months']);
            $this->assertSame('2028-10-31', $updated['renewal_date']);
            $this->assertNull($order->fresh()->renewal_snapshot_at);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_partial_activation_captures_snapshot_only_after_last_line(): void
    {
        CarbonImmutable::setTestNow('2026-11-18 12:00:00');

        try {
            $tenant = Tenant::factory()->create();
            $user = User::factory()->create();
            $tenant->users()->attach($user, ['role' => 'OWNER', 'status' => 'ACTIVE']);
            Subscription::factory()->for($tenant)->trialing()->create();
            $operator = Operator::factory()->create();
            $product = ProductFactory::new()->create([
                'tenant_id' => $tenant->id, 'operator_id' => $operator->id,
            ]);
            $client = Client::query()->create([
                'tenant_id' => $tenant->id, 'person_type' => 'PJ',
                'document' => '12345678000195', 'company_name' => 'Cliente parcial',
                'is_active' => true,
            ]);
            $classification = OperatorClassification::query()->create([
                'tenant_id' => $tenant->id, 'operator_id' => $operator->id,
                'code' => 'PARTIAL-SNAPSHOT', 'name' => 'Parcial', 'is_active' => true,
            ]);
            ClientOperatorClassification::query()->create([
                'tenant_id' => $tenant->id, 'client_id' => $client->id,
                'operator_id' => $operator->id, 'classification_id' => $classification->id,
            ]);
            ClassificationRenewalRule::query()->create([
                'tenant_id' => $tenant->id, 'classification_id' => $classification->id,
                'minimum_months' => 18, 'valid_from' => '2026-01-01',
                'is_active' => true,
            ]);
            foreach ([
                ['name' => 'Parcialmente ativado', 'code' => OrderStatusCode::PartiallyActivated->value],
                ['name' => 'Concluído', 'code' => OrderStatusCode::Completed->value],
            ] as $status) {
                OrderStatus::query()->create([...$status, 'is_initial' => false]);
            }
            $order = CommercialOrder::query()->create([
                'tenant_id' => $tenant->id, 'client_id' => $client->id,
                'operator_id' => $operator->id, 'product_id' => $product->id,
                'code' => 'SNAP-PARTIAL-FLOW', 'order_type' => 'PORTABILITY',
                'customer_name' => 'Cliente', 'monthly_amount' => 200,
                'line_quantity' => 2, 'sold_on' => '2026-10-01',
            ]);
            $first = $order->items()->create([
                'product_id' => $product->id, 'description' => 'Linha 1',
                'quantity' => 1, 'monthly_unit_amount' => 100, 'status' => 'PENDING',
            ]);
            $last = $order->items()->create([
                'product_id' => $product->id, 'description' => 'Linha 2',
                'quantity' => 1, 'monthly_unit_amount' => 100, 'status' => 'PENDING',
            ]);

            $activate = app(ActivateOrderItemsAction::class);
            $activate->execute($tenant, $user, $order->id, [
                'item_ids' => [$first->id], 'activated_at' => '2026-10-01',
            ]);
            $this->assertNull($order->fresh()->administratively_completed_at);
            $this->assertNull($order->fresh()->renewal_snapshot_at);

            $activate->execute($tenant, $user, $order->id, [
                'item_ids' => [$last->id], 'activated_at' => '2026-10-31',
            ]);
            $completed = $order->fresh();
            $this->assertNotNull($completed->administratively_completed_at);
            $this->assertSame(18, $completed->renewal_months_snapshot);
            $this->assertSame('2028-04-16', $completed->renewal_date_snapshot->toDateString());
            $this->assertNotNull($completed->renewal_snapshot_at);
            $renewal = app(ListClientContractRenewalsAction::class)->execute($client)->first();
            $this->assertSame('2026-10-16', $renewal['average_activation_date']);
            $this->assertSame(18, $renewal['renewal_months']);
            $this->assertSame('2028-04-16', $renewal['renewal_date']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_administrative_completion_captures_renewal_snapshot(): void
    {
        CarbonImmutable::setTestNow('2026-11-18 12:00:00');

        try {
            $tenant = Tenant::factory()->create();
            $user = User::factory()->create();
            $tenant->users()->attach($user, ['role' => 'OWNER', 'status' => 'ACTIVE']);
            Subscription::factory()->for($tenant)->trialing()->create();
            $operator = Operator::factory()->create();
            $product = ProductFactory::new()->create([
                'tenant_id' => $tenant->id, 'operator_id' => $operator->id,
            ]);
            $client = Client::query()->create([
                'tenant_id' => $tenant->id, 'person_type' => 'PJ',
                'document' => '12345678000195', 'company_name' => 'Cliente integração',
                'is_active' => true,
            ]);
            $classification = OperatorClassification::query()->create([
                'tenant_id' => $tenant->id, 'operator_id' => $operator->id,
                'code' => 'INTEGRATION', 'name' => 'Integração', 'is_active' => true,
            ]);
            ClientOperatorClassification::query()->create([
                'tenant_id' => $tenant->id, 'client_id' => $client->id,
                'operator_id' => $operator->id, 'classification_id' => $classification->id,
            ]);
            ClassificationRenewalRule::query()->create([
                'tenant_id' => $tenant->id, 'classification_id' => $classification->id,
                'minimum_months' => 18, 'valid_from' => '2026-01-01',
                'is_active' => true,
            ]);
            OrderStatus::query()->create([
                'name' => 'Concluído', 'code' => OrderStatusCode::Completed->value,
                'is_initial' => false,
            ]);
            $order = CommercialOrder::query()->create([
                'tenant_id' => $tenant->id, 'client_id' => $client->id,
                'operator_id' => $operator->id, 'product_id' => $product->id,
                'code' => 'SNAP-COMPLETE', 'order_type' => 'PORTABILITY',
                'customer_name' => 'Cliente', 'monthly_amount' => 100,
                'line_quantity' => 1, 'sold_on' => '2026-10-01',
            ]);
            $order->items()->create([
                'product_id' => $product->id, 'description' => 'Linha',
                'quantity' => 1, 'monthly_unit_amount' => 100,
                'status' => 'ACTIVE', 'activated_at' => '2026-10-31',
            ]);

            app(CompleteOrderAction::class)->execute($tenant, $user, $order->id);

            $completed = $order->fresh();
            $this->assertNotNull($completed->administratively_completed_at);
            $this->assertSame(18, $completed->renewal_months_snapshot);
            $this->assertSame('2028-04-30', $completed->renewal_date_snapshot->toDateString());
            $this->assertNotNull($completed->renewal_snapshot_at);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_missing_rule_does_not_invent_renewal_conditions(): void
    {
        $tenant = Tenant::factory()->create();
        $operator = Operator::factory()->create();
        $product = ProductFactory::new()->create([
            'tenant_id' => $tenant->id, 'operator_id' => $operator->id,
        ]);
        $client = Client::query()->create([
            'tenant_id' => $tenant->id,
            'person_type' => 'PJ',
            'document' => '12345678000195',
            'company_name' => 'Cliente sem regra',
            'is_active' => true,
        ]);
        $order = CommercialOrder::query()->create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id,
            'operator_id' => $operator->id, 'product_id' => $product->id,
            'code' => 'SNAP-NO-RULE', 'order_type' => 'PORTABILITY',
            'customer_name' => 'Cliente', 'monthly_amount' => 100,
            'line_quantity' => 1, 'sold_on' => '2026-10-01',
        ]);
        $item = $order->items()->create([
            'product_id' => $product->id, 'description' => 'Linha',
            'quantity' => 1, 'monthly_unit_amount' => 100,
            'status' => 'ACTIVE', 'activated_at' => '2026-10-31',
        ]);

        app(CaptureOrderRenewalSnapshotAction::class)->execute($order, collect([$item]));

        $this->assertNull($order->fresh()->renewal_snapshot_at);
        $this->assertNull($order->fresh()->renewal_months_snapshot);
        $this->assertNull($order->fresh()->renewal_date_snapshot);
    }

    public function test_incomplete_activation_does_not_capture_renewal(): void
    {
        $tenant = Tenant::factory()->create();
        $operator = Operator::factory()->create();
        $product = ProductFactory::new()->create([
            'tenant_id' => $tenant->id, 'operator_id' => $operator->id,
        ]);
        $client = Client::query()->create([
            'tenant_id' => $tenant->id, 'person_type' => 'PJ',
            'document' => '12345678000195', 'company_name' => 'Cliente parcial',
            'is_active' => true,
        ]);
        $order = CommercialOrder::query()->create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id,
            'operator_id' => $operator->id, 'product_id' => $product->id,
            'code' => 'SNAP-PARTIAL', 'order_type' => 'PORTABILITY',
            'customer_name' => 'Cliente', 'monthly_amount' => 100,
            'line_quantity' => 1, 'sold_on' => '2026-10-01',
        ]);
        $pending = $order->items()->create([
            'product_id' => $product->id, 'description' => 'Linha',
            'quantity' => 1, 'monthly_unit_amount' => 100, 'status' => 'PENDING',
        ]);

        app(CaptureOrderRenewalSnapshotAction::class)->execute($order, collect([$pending]));

        $this->assertNull($order->fresh()->renewal_snapshot_at);
    }

    public function test_completed_order_keeps_original_term_after_rule_change(): void
    {
        CarbonImmutable::setTestNow('2026-11-18 12:00:00');

        try {
            $tenant = Tenant::factory()->create();
            $operator = Operator::factory()->create();
            $product = ProductFactory::new()->create([
                'tenant_id' => $tenant->id,
                'operator_id' => $operator->id,
            ]);
            $client = Client::query()->create([
                'tenant_id' => $tenant->id,
                'person_type' => 'PJ',
                'document' => '12345678000195',
                'company_name' => 'Cliente de teste',
                'is_active' => true,
            ]);
            $classification = OperatorClassification::query()->create([
                'tenant_id' => $tenant->id, 'operator_id' => $operator->id,
                'code' => 'GOLD', 'name' => 'Gold', 'is_active' => true,
            ]);
            ClientOperatorClassification::query()->create([
                'tenant_id' => $tenant->id, 'client_id' => $client->id,
                'operator_id' => $operator->id, 'classification_id' => $classification->id,
            ]);
            $rule = ClassificationRenewalRule::query()->create([
                'tenant_id' => $tenant->id, 'classification_id' => $classification->id,
                'minimum_months' => 18, 'valid_from' => '2026-01-01',
                'valid_until' => null, 'is_active' => true,
            ]);
            $order = CommercialOrder::query()->create([
                'tenant_id' => $tenant->id, 'client_id' => $client->id,
                'operator_id' => $operator->id, 'product_id' => $product->id, 'code' => 'SNAP-001',
                'order_type' => 'PORTABILITY', 'customer_name' => 'Cliente',
                'monthly_amount' => 100, 'line_quantity' => 1,
                'sold_on' => '2026-10-01',
            ]);
            $item = $order->items()->create([
                'product_id' => $product->id,
                'description' => 'Linha', 'quantity' => 1,
                'monthly_unit_amount' => 100, 'status' => 'ACTIVE',
                'activated_at' => '2026-10-31',
            ]);

            $capture = app(CaptureOrderRenewalSnapshotAction::class);
            $capture->execute($order, collect([$item]));
            $this->assertSame(18, $order->fresh()->renewal_months_snapshot);
            $this->assertSame('2028-04-30', $order->fresh()->renewal_date_snapshot->toDateString());

            $rule->update(['minimum_months' => 24, 'is_active' => false]);
            $capture->execute($order->fresh(), collect([$item]));
            $this->assertSame(18, $order->fresh()->renewal_months_snapshot);
            $this->assertSame('2028-04-30', $order->fresh()->renewal_date_snapshot->toDateString());
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
