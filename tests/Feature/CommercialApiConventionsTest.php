<?php

namespace Tests\Feature;

use App\Models\CommercialParticipant;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Database\Factories\OperatorFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommercialApiConventionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function company(string $role = 'OWNER'): Tenant
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $tenant->users()->attach($user, ['role' => $role, 'status' => 'ACTIVE']);
        Subscription::factory()->for($tenant)->trialing()->create();
        $this->actingAs($user);

        return $tenant;
    }

    public function test_participant_filters_preserve_pagination_and_exclude_other_tenants(): void
    {
        $tenant = $this->company();
        $participant = CommercialParticipant::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Parceiro procurado', 'is_active' => false]);
        CommercialParticipant::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Parceiro ativo']);
        CommercialParticipant::factory()->create(['name' => 'Parceiro de outra empresa', 'is_active' => false]);

        $this->getJson("/api/v1/tenants/{$tenant->id}/commercial-participants?search=Parceiro&role=PARTNER&is_active=0")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $participant->id)
            ->assertJsonPath('data.0.is_active', false)->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.current_page', 1)->assertJsonPath('meta.can_manage', true);
    }

    public function test_participant_write_returns_resource_and_persists_only_validated_fields(): void
    {
        $tenant = $this->company();
        $other = Tenant::factory()->create();

        $this->postJson("/api/v1/tenants/{$tenant->id}/commercial-participants", [
            'role' => 'PARTNER', 'person_type' => 'PF', 'name' => 'Novo parceiro', 'is_active' => true,
            'tenant_id' => $other->id,
        ])->assertOk()->assertJsonPath('data.name', 'Novo parceiro')->assertJsonPath('data.tenant_id', $tenant->id);

        $this->assertDatabaseHas('commercial_participants', ['tenant_id' => $tenant->id, 'name' => 'Novo parceiro']);
        $this->assertDatabaseMissing('commercial_participants', ['tenant_id' => $other->id, 'name' => 'Novo parceiro']);
    }

    public function test_participant_from_another_tenant_cannot_be_edited(): void
    {
        $tenant = $this->company();
        $other = CommercialParticipant::factory()->create(['name' => 'Original']);

        $this->patchJson("/api/v1/tenants/{$tenant->id}/commercial-participants/{$other->id}", [
            'role' => 'PARTNER', 'person_type' => 'PF', 'name' => 'Alterado', 'is_active' => true,
        ])->assertNotFound();

        $this->assertDatabaseHas('commercial_participants', ['id' => $other->id, 'name' => 'Original']);
    }

    public function test_list_filters_and_write_inputs_are_validated(): void
    {
        $tenant = $this->company();

        $this->getJson("/api/v1/tenants/{$tenant->id}/commercial-participants?role=invalid&page=0")
            ->assertUnprocessable()->assertInvalid(['role', 'page']);
        $this->getJson("/api/v1/tenants/{$tenant->id}/commercial-goals?metric=invalid")
            ->assertUnprocessable()->assertInvalid(['metric']);
        $this->getJson("/api/v1/tenants/{$tenant->id}/team-commissions?commercial_role=invalid")
            ->assertUnprocessable()->assertInvalid(['commercial_role']);
        $this->getJson("/api/v1/tenants/{$tenant->id}/commissions?order_type=invalid")
            ->assertUnprocessable()->assertInvalid(['order_type']);
        $this->getJson("/api/v1/tenants/{$tenant->id}/commercial-orders?product_id=invalid")
            ->assertUnprocessable()->assertInvalid(['product_id']);
        $this->postJson("/api/v1/tenants/{$tenant->id}/commercial-orders", [])
            ->assertUnprocessable()->assertInvalid(['operator_id', 'product_id', 'participants']);
        $this->postJson("/api/v1/tenants/{$tenant->id}/commercial-participants", [])
            ->assertUnprocessable()->assertInvalid(['role', 'person_type', 'name', 'is_active']);

        $this->assertDatabaseCount('commercial_orders', 0);
        $this->assertDatabaseCount('commercial_participants', 0);
    }

    public function test_commission_settings_keep_their_existing_envelope(): void
    {
        $tenant = $this->company();

        $this->putJson("/api/v1/tenants/{$tenant->id}/commissions/settings", ['default_deduction_percentage' => 15])
            ->assertOk()->assertJsonPath('data.default_deduction_percentage', 15);

        $this->assertDatabaseHas('tenant_commission_settings', ['tenant_id' => $tenant->id, 'default_deduction_percentage' => 15]);
        $this->putJson("/api/v1/tenants/{$tenant->id}/commissions/settings", ['default_deduction_percentage' => 101])
            ->assertUnprocessable()->assertInvalid(['default_deduction_percentage']);
    }

    public function test_consultant_cannot_manage_participants(): void
    {
        $tenant = $this->company('CONSULTANT');

        $this->postJson("/api/v1/tenants/{$tenant->id}/commercial-participants", [
            'role' => 'PARTNER', 'person_type' => 'PF', 'name' => 'Parceiro', 'is_active' => true,
        ])->assertForbidden();

        $this->assertDatabaseCount('commercial_participants', 0);
    }

    public function test_guest_cannot_list_participants(): void
    {
        $tenant = Tenant::factory()->create();

        $this->getJson("/api/v1/tenants/{$tenant->id}/commercial-participants")->assertUnauthorized();
    }

    public function test_goal_resources_preserve_tiers_dates_and_filters(): void
    {
        $tenant = $this->company();
        $operator = OperatorFactory::new()->create();
        $tenant->operators()->attach($operator);
        $payload = ['operator_id' => $operator->id, 'order_type' => 'NEW', 'commercial_role' => 'PARTNER',
            'metric' => 'QUANTITY', 'starts_on' => '2026-11-01', 'is_active' => true,
            'tiers' => [['minimum' => 0, 'percentage' => 5], ['minimum' => 10, 'percentage' => 8]]];

        $this->postJson("/api/v1/tenants/{$tenant->id}/commercial-goals", $payload)
            ->assertOk()->assertJsonPath('data.starts_on', '2026-11-01')->assertJsonCount(2, 'data.tiers')
            ->assertJsonPath('data.operator.id', $operator->id);

        $this->assertDatabaseHas('commercial_goals', ['tenant_id' => $tenant->id, 'metric' => 'QUANTITY']);
        $this->getJson("/api/v1/tenants/{$tenant->id}/commercial-goals?metric=QUANTITY&is_active=1")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonCount(2, 'data.0.tiers');
        $this->getJson("/api/v1/tenants/{$tenant->id}/commercial-goals?metric=REVENUE")
            ->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/tenants/{$tenant->id}/commercial-goals", $payload)
            ->assertUnprocessable()->assertInvalid(['starts_on']);
    }

    public function test_commission_rule_and_override_resources_preserve_percentage_and_defaults(): void
    {
        $tenant = $this->company();
        $operator = OperatorFactory::new()->create();
        $tenant->operators()->attach($operator);
        $participant = CommercialParticipant::factory()->create(['tenant_id' => $tenant->id]);
        $payload = ['operator_id' => $operator->id, 'order_type' => 'NEW', 'commercial_role' => 'PARTNER',
            'percentage' => 5, 'starts_on' => '2026-11-01', 'is_active' => true];

        $this->postJson("/api/v1/tenants/{$tenant->id}/team-commissions", $payload)
            ->assertOk()->assertJsonPath('data.percentage', '5.00')->assertJsonPath('data.operator.id', $operator->id);

        $this->assertDatabaseHas('team_commission_rules', ['tenant_id' => $tenant->id, 'commercial_role' => 'PARTNER']);
        $this->postJson("/api/v1/tenants/{$tenant->id}/commercial-participants/{$participant->id}/commissions", $payload)
            ->assertOk()->assertJsonPath('data.percentage', '5.00');
        $this->assertDatabaseHas('commercial_participant_commissions', ['commercial_participant_id' => $participant->id, 'tenant_id' => $tenant->id]);
        $this->getJson("/api/v1/tenants/{$tenant->id}/commercial-participants/{$participant->id}/commissions")
            ->assertOk()->assertJsonCount(1, 'data.defaults')->assertJsonCount(1, 'data.overrides');
        $this->getJson("/api/v1/tenants/{$tenant->id}/team-commissions?commercial_role=CONSULTANT")
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_commission_table_resources_and_filters_keep_existing_contract(): void
    {
        $tenant = $this->company();
        $operator = OperatorFactory::new()->create();
        $tenant->operators()->attach($operator);

        $this->postJson("/api/v1/tenants/{$tenant->id}/commissions", [
            'operator_id' => $operator->id, 'order_type' => 'NEW', 'percentage' => 12, 'is_active' => false,
        ])->assertOk()->assertJsonPath('data.percentage', '12.00')->assertJsonPath('data.name', 'Novo');

        $this->assertDatabaseHas('commission_tables', ['tenant_id' => $tenant->id, 'operator_id' => $operator->id, 'percentage' => 12]);
        $this->getJson("/api/v1/tenants/{$tenant->id}/commissions?is_active=0&order_type=NEW")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.operator.id', $operator->id);
        $this->getJson("/api/v1/tenants/{$tenant->id}/commissions?is_active=1")
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_order_resources_preserve_participants_and_date_and_filter_by_customer(): void
    {
        $tenant = $this->company();
        $operator = OperatorFactory::new()->create();
        $tenant->operators()->attach($operator);
        $participant = CommercialParticipant::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Parceiro comercial']);
        $productId = $this->postJson("/api/v1/tenants/{$tenant->id}/products", [
            'operator_id' => $operator->id, 'name' => 'Plano', 'category' => 'POSTPAID', 'price' => 100,
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/tenants/{$tenant->id}/commercial-orders", [
            'operator_id' => $operator->id, 'product_id' => $productId, 'order_type' => 'NEW',
            'customer_name' => 'Cliente teste', 'monthly_amount' => 100, 'line_quantity' => 1, 'sold_on' => '2026-11-01',
            'participants' => [['role' => 'PARTNER', 'commercial_participant_id' => $participant->id]],
        ])->assertOk()->assertJsonPath('data.monthly_amount', '100.00')->assertJsonPath('data.sold_on', '2026-11-01')
            ->assertJsonPath('data.participants.0.participant_name', 'Parceiro comercial');

        $this->assertDatabaseHas('commercial_orders', ['tenant_id' => $tenant->id, 'customer_name' => 'Cliente teste', 'status' => 'DRAFT']);
        $this->getJson("/api/v1/tenants/{$tenant->id}/commercial-orders?search=Cliente&status=DRAFT")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonCount(1, 'data.0.participants');
        $this->getJson("/api/v1/tenants/{$tenant->id}/commercial-orders?search=ausente")
            ->assertOk()->assertJsonCount(0, 'data');
    }
}
