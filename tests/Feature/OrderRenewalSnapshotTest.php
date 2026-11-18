<?php

namespace Tests\Feature;

use App\Actions\Commercial\CaptureOrderRenewalSnapshotAction;
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

class OrderRenewalSnapshotTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_completed_order_keeps_original_term_after_rule_change(): void
    {
        CarbonImmutable::setTestNow('2026-11-18 12:00:00');

        try {
            $tenant = Tenant::factory()->create();
            $operator = Operator::factory()->create();
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
                'operator_id' => $operator->id, 'code' => 'SNAP-001',
                'order_type' => 'PORTABILITY', 'customer_name' => 'Cliente',
                'monthly_amount' => 100, 'line_quantity' => 1,
                'sold_on' => '2026-10-01',
            ]);
            $item = $order->items()->create([
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
