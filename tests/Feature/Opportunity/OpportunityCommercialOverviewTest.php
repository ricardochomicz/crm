<?php

namespace Tests\Feature\Opportunity;

use App\Actions\Opportunity\OpportunityAction;
use App\Models\Client;
use App\Models\CommercialOrder;
use App\Models\CommercialOrderItem;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Database\Factories\OperatorFactory;
use Database\Factories\ProductFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OpportunityCommercialOverviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function member(Tenant $tenant, string $role = 'CONSULTANT'): TenantUser
    {
        return TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => User::factory()->create()->id,
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }

    private function opportunity(Tenant $tenant, TenantUser $member, string $status, string $closedAt): Opportunity
    {
        $client = Client::query()->create([
            'tenant_id' => $tenant->id,
            'person_type' => 'PF',
            'document' => (string) random_int(10000000000, 99999999999),
            'company_name' => 'Cliente de teste',
        ]);

        return Opportunity::query()->create([
            'tenant_id' => $tenant->id,
            'tenant_user_id' => $member->id,
            'client_id' => $client->id,
            'code' => 'OV-' . Str::random(12),
            'title' => 'Venda teste',
            'order_type' => 'NEW',
            'status' => $status,
            'closed_at' => $closedAt,
            'estimated_monthly_amount' => 150,
        ]);
    }

    private function linkOrder(Tenant $tenant, Opportunity $opportunity): CommercialOrder
    {
        $operator = OperatorFactory::new()->create();
        $product = ProductFactory::new()->create([
            'tenant_id' => $tenant->id,
            'operator_id' => $operator->id,
        ]);

        return CommercialOrder::query()->create([
            'tenant_id' => $tenant->id,
            'opportunity_id' => $opportunity->id,
            'operator_id' => $operator->id,
            'product_id' => $product->id,
            'order_type' => 'NEW',
            'customer_name' => 'Cliente de teste',
            'monthly_amount' => 100,
            'line_quantity' => 1,
            'status' => 'DRAFT',
            'sold_on' => '2026-11-10',
        ]);
    }

    public function test_won_with_orders_respects_period_team_visibility_and_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->member($tenant, 'OWNER');
        $consultant = $this->member($tenant);
        $otherConsultant = $this->member($tenant);

        $linked = $this->opportunity($tenant, $consultant, 'won', '2026-11-10 10:00:00');
        $this->linkOrder($tenant, $linked);
        $order = $linked->commercialOrder;
        foreach ([
            ['status' => 'ACTIVE', 'activated_at' => '2026-11-10', 'monthly_unit_amount' => 80],
            ['status' => 'ACTIVE', 'activated_at' => '2026-10-10', 'monthly_unit_amount' => 90],
            ['status' => 'PENDING', 'activated_at' => null, 'monthly_unit_amount' => 100],
        ] as $line) {
            CommercialOrderItem::query()->create([
                'commercial_order_id' => $order->id,
                'product_id' => $order->product_id,
                'operator_id' => $order->operator_id,
                'description' => 'Linha de teste',
                'quantity' => 1,
                ...$line,
            ]);
        }
        $this->opportunity($tenant, $consultant, 'won', '2026-11-11 10:00:00');
        $outsidePeriod = $this->opportunity($tenant, $consultant, 'won', '2026-10-10 10:00:00');
        $this->linkOrder($tenant, $outsidePeriod);
        $lost = $this->opportunity($tenant, $consultant, 'lost', '2026-11-12 10:00:00');
        $this->linkOrder($tenant, $lost);
        $other = $this->opportunity($tenant, $otherConsultant, 'won', '2026-11-10 10:00:00');
        $this->linkOrder($tenant, $other);

        $foreignTenant = Tenant::factory()->create();
        $foreignMember = $this->member($foreignTenant);
        $foreign = $this->opportunity($foreignTenant, $foreignMember, 'won', '2026-11-10 10:00:00');
        $this->linkOrder($foreignTenant, $foreign);

        $action = app(OpportunityAction::class);
        $period = ['date_from' => '2026-11-01', 'date_to' => '2026-11-30'];

        $own = $action->overview($tenant, $consultant->user, $period);
        $this->assertSame(2, $own['won']['count']);
        $this->assertSame(1, $own['won_with_orders']['count']);
        $this->assertEquals(150, $own['won_with_orders']['monthly']);
        $this->assertSame(1, $own['activated_lines']['count']);
        $this->assertEquals(80, $own['activated_lines']['monthly']);

        $all = $action->overview($tenant, $owner->user, $period);
        $this->assertSame(3, $all['won']['count']);
        $this->assertSame(2, $all['won_with_orders']['count']);
        $this->assertEquals(300, $all['won_with_orders']['monthly']);
        $this->assertSame(1, $all['activated_lines']['count']);
    }
}
