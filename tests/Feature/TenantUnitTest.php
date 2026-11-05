<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\TenantUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TenantUnitTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function company(string $role = 'OWNER'): Tenant
    {
        $tenant = Tenant::factory()->create();
        $tenant->units()->create(['name' => 'Matriz', 'is_headquarters' => true]);
        $user = User::factory()->create();
        $tenant->users()->attach($user, ['role' => $role, 'status' => 'ACTIVE']);
        Subscription::factory()->for($tenant)->trialing()->create();
        $this->actingAs($user);

        return $tenant;
    }

    public function test_owner_creates_branch_under_existing_subscription_and_cannot_create_second_headquarters(): void
    {
        $tenant = $this->company();

        $this->postJson("/api/v1/tenants/{$tenant->id}/units", ['name' => 'Filial Centro', 'is_headquarters' => true, 'tenant_id' => 'invalid'])
            ->assertCreated()->assertJsonPath('data.name', 'Filial Centro');

        $this->assertDatabaseHas('tenant_units', ['tenant_id' => $tenant->id, 'name' => 'Filial Centro', 'is_headquarters' => null]);
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseCount('tenant_units', 2);
    }

    public function test_owner_edits_unit_and_reads_members_and_teams(): void
    {
        $tenant = $this->company();
        $unit = $tenant->units()->first();

        $this->patchJson("/api/v1/tenants/{$tenant->id}/units/{$unit->id}", ['name' => 'Sede', 'email' => 'sede@example.com'])->assertNoContent();

        $this->assertDatabaseHas('tenant_units', ['id' => $unit->id, 'name' => 'Sede', 'is_headquarters' => true]);
        $this->getJson("/api/v1/tenants/{$tenant->id}/units")->assertOk()->assertJsonPath('data.0.name', 'Sede')->assertJsonPath('meta.can_manage', true)->assertJsonCount(1, 'meta.members');
    }

    public function test_member_sees_only_linked_units_and_cannot_manage_them(): void
    {
        $tenant = $this->company('CONSULTANT');
        $tenant->units()->create(['name' => 'Filial privada']);

        $this->getJson("/api/v1/tenants/{$tenant->id}/units")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Matriz')->assertJsonCount(0, 'meta.members');
        $this->postJson("/api/v1/tenants/{$tenant->id}/units", ['name' => 'Filial'])->assertForbidden();
    }

    public function test_team_assignment_includes_members_and_supervisor_and_preserves_subscription(): void
    {
        $tenant = $this->company();
        $headquarters = $tenant->units()->first();
        $branch = $tenant->units()->create(['name' => 'Filial']);
        $membership = $tenant->users()->first()->membership;
        $team = Team::factory()->create(['tenant_id' => $tenant->id, 'tenant_unit_id' => $headquarters->id, 'supervisor_membership_id' => $membership->id]);
        $membership->update(['team_id' => $team->id]);

        $this->putJson("/api/v1/tenants/{$tenant->id}/units/{$branch->id}/assignments", ['membership_ids' => [], 'team_ids' => [$team->id]])->assertNoContent();

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'tenant_unit_id' => $branch->id]);
        $this->assertDatabaseHas('tenant_unit_memberships', ['tenant_unit_id' => $branch->id, 'membership_id' => $membership->id]);
        $this->assertDatabaseHas('tenant_unit_memberships', ['tenant_unit_id' => $headquarters->id, 'membership_id' => $membership->id]);
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_removed_team_returns_to_headquarters(): void
    {
        $tenant = $this->company();
        $headquarters = $tenant->units()->first();
        $branch = $tenant->units()->create(['name' => 'Filial']);
        $team = Team::factory()->create(['tenant_id' => $tenant->id, 'tenant_unit_id' => $branch->id, 'supervisor_membership_id' => $tenant->users()->first()->membership->id]);

        $this->putJson("/api/v1/tenants/{$tenant->id}/units/{$branch->id}/assignments", ['membership_ids' => [], 'team_ids' => []])->assertNoContent();

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'tenant_unit_id' => $headquarters->id]);
    }

    public function test_unit_and_member_from_other_company_are_rejected(): void
    {
        $tenant = $this->company();
        $unit = $tenant->units()->first();
        $other = TenantUnit::factory()->create();
        $otherUser = User::factory()->create();
        $other->tenant->users()->attach($otherUser, ['role' => 'CONSULTANT', 'status' => 'ACTIVE']);
        $foreignMembership = $other->tenant->users()->first()->membership;

        $this->patchJson("/api/v1/tenants/{$tenant->id}/units/{$other->id}", ['name' => 'Alterado'])->assertNotFound();
        $this->putJson("/api/v1/tenants/{$tenant->id}/units/{$unit->id}/assignments", ['membership_ids' => [$foreignMembership->id], 'team_ids' => []])->assertUnprocessable()->assertInvalid(['membership_ids.0']);
    }

    public function test_expired_subscription_prevents_unit_creation(): void
    {
        $tenant = $this->company();
        $tenant->subscription->update(['trial_ends_at' => now()->subDay()]);

        $this->postJson("/api/v1/tenants/{$tenant->id}/units", ['name' => 'Filial'])->assertForbidden();

        $this->assertDatabaseCount('tenant_units', 1);
    }

    public function test_invalid_unit_fields_are_rejected(): void
    {
        $tenant = $this->company();

        $this->postJson("/api/v1/tenants/{$tenant->id}/units", ['name' => '', 'email' => 'invalid'])->assertUnprocessable()->assertInvalid(['name', 'email']);
    }

    public function test_migration_assigns_existing_members_and_teams_to_headquarters(): void
    {
        $tenant = $this->company();
        $team = Team::factory()->create(['tenant_id' => $tenant->id]);
        $migration = require database_path('migrations/2026_11_05_125421_create_tenant_units_and_assignments.php');
        $migration->down();

        $migration->up();

        $headquarters = $tenant->units()->sole();
        $this->assertDatabaseHas('teams', ['id' => $team->id, 'tenant_unit_id' => $headquarters->id]);
        $this->assertDatabaseCount('tenant_unit_memberships', 2);
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_guest_cannot_access_units(): void
    {
        $tenant = Tenant::factory()->create();

        $this->getJson("/api/v1/tenants/{$tenant->id}/units")->assertUnauthorized();
    }
}
