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
        $this->getJson("/api/v1/tenants/{$tenant->id}/units")->assertOk()->assertJsonPath('data.0.name', 'Sede')->assertJsonPath('meta.can_manage', true)->assertJsonMissingPath('meta.members');
    }

    public function test_member_sees_only_linked_units_and_cannot_manage_them(): void
    {
        $tenant = $this->company('CONSULTANT');
        $tenant->units()->create(['name' => 'Filial privada']);

        $this->getJson("/api/v1/tenants/{$tenant->id}/units")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Matriz')->assertJsonMissingPath('meta.members');
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

        $this->patchJson("/api/v1/tenants/{$tenant->id}/units/{$branch->id}/assignments", ['kind' => 'teams', 'operation' => 'add', 'ids' => [$team->id]])->assertNoContent();

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

        $this->patchJson("/api/v1/tenants/{$tenant->id}/units/{$branch->id}/assignments", ['kind' => 'teams', 'operation' => 'remove', 'ids' => [$team->id]])->assertNoContent();

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
        $this->patchJson("/api/v1/tenants/{$tenant->id}/units/{$unit->id}/assignments", ['kind' => 'members', 'operation' => 'add', 'ids' => [$foreignMembership->id]])->assertUnprocessable()->assertInvalid(['ids.0']);
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

    public function test_assignment_search_is_paginated_and_can_find_email(): void
    {
        $tenant = $this->company();
        $unit = $tenant->units()->first();
        foreach (range(1, 24) as $number) {
            $tenant->users()->attach(User::factory()->create(['name' => 'Pessoa '.$number, 'email' => 'pessoa'.$number.'@example.com']), ['role' => 'CONSULTANT', 'status' => 'ACTIVE']);
        }

        $url = "/api/v1/tenants/{$tenant->id}/units/{$unit->id}/assignments?kind=members&mode=linked";
        $this->getJson($url)->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('total', 25)->assertJsonPath('last_page', 2);
        $this->getJson($url.'&page=2')->assertOk()->assertJsonCount(5, 'data');
        $this->getJson($url.'&q=pessoa24%40example.com')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.name', 'Pessoa 24');
        $this->getJson(str_replace('mode=linked', 'mode=available', $url))->assertOk()->assertJsonPath('total', 0);
    }

    public function test_incremental_changes_preserve_other_members_and_other_units(): void
    {
        $tenant = $this->company();
        $unit = $tenant->units()->create(['name' => 'Filial']);
        $owner = $tenant->users()->first()->membership;
        $user = User::factory()->create();
        $tenant->users()->attach($user, ['role' => 'CONSULTANT', 'status' => 'ACTIVE']);
        $member = $tenant->users()->whereKey($user->id)->first()->membership;
        $unit->members()->attach($owner->id, ['tenant_id' => $tenant->id]);
        $url = "/api/v1/tenants/{$tenant->id}/units/{$unit->id}/assignments";

        $this->patchJson($url, ['kind' => 'members', 'operation' => 'add', 'ids' => [$member->id]])->assertNoContent();
        $this->assertDatabaseHas('tenant_unit_memberships', ['tenant_unit_id' => $unit->id, 'membership_id' => $owner->id]);
        $this->patchJson($url, ['kind' => 'members', 'operation' => 'remove', 'ids' => [$member->id]])->assertNoContent();
        $this->assertDatabaseMissing('tenant_unit_memberships', ['tenant_unit_id' => $unit->id, 'membership_id' => $member->id]);
        $this->assertDatabaseHas('tenant_unit_memberships', ['tenant_unit_id' => $unit->id, 'membership_id' => $owner->id]);
        $this->assertDatabaseHas('tenant_unit_memberships', ['tenant_unit_id' => $tenant->units()->where('is_headquarters', true)->first()->id, 'membership_id' => $member->id]);
    }

    public function test_team_members_cannot_be_removed_from_their_unit(): void
    {
        $tenant = $this->company();
        $unit = $tenant->units()->first();
        $owner = $tenant->users()->first()->membership;
        Team::factory()->create(['tenant_id' => $tenant->id, 'tenant_unit_id' => $unit->id, 'supervisor_membership_id' => $owner->id]);

        $url = "/api/v1/tenants/{$tenant->id}/units/{$unit->id}/assignments";
        $this->patchJson($url, ['kind' => 'members', 'operation' => 'remove', 'ids' => [$owner->id]])->assertUnprocessable()->assertInvalid(['ids']);
        $this->getJson($url.'?kind=members&mode=linked')->assertOk()->assertJsonPath('data.0.removal_blocked_reason', 'Este membro integra ou supervisiona uma equipe desta unidade. Atualize a equipe antes de remover o vínculo.');
        $this->assertDatabaseHas('tenant_unit_memberships', ['tenant_unit_id' => $unit->id, 'membership_id' => $owner->id]);
    }

    public function test_team_search_shows_origin_and_transfers_only_selected_teams(): void
    {
        $tenant = $this->company();
        $headquarters = $tenant->units()->first();
        $branch = $tenant->units()->create(['name' => 'Filial']);
        $first = Team::factory()->create(['name' => 'Vendas', 'tenant_id' => $tenant->id, 'tenant_unit_id' => $headquarters->id]);
        $second = Team::factory()->create(['name' => 'Suporte', 'tenant_id' => $tenant->id, 'tenant_unit_id' => $headquarters->id]);
        $url = "/api/v1/tenants/{$tenant->id}/units/{$branch->id}/assignments";

        $this->getJson($url.'?kind=teams&mode=available&q=Vendas')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.unit_name', 'Matriz');
        $this->patchJson($url, ['kind' => 'teams', 'operation' => 'add', 'ids' => [$first->id]])->assertNoContent();
        $this->assertDatabaseHas('teams', ['id' => $first->id, 'tenant_unit_id' => $branch->id]);
        $this->assertDatabaseHas('teams', ['id' => $second->id, 'tenant_unit_id' => $headquarters->id]);
    }

    public function test_assignment_list_enforces_permissions_and_tenant_scope(): void
    {
        $tenant = $this->company();
        $other = TenantUnit::factory()->create();
        $this->getJson("/api/v1/tenants/{$tenant->id}/units/{$other->id}/assignments?kind=members&mode=linked")->assertNotFound();
        $limited = $this->company('CONSULTANT');
        $unit = $limited->units()->first();
        $this->getJson("/api/v1/tenants/{$limited->id}/units/{$unit->id}/assignments?kind=members&mode=linked")->assertForbidden();
    }

    public function test_guest_cannot_access_units(): void
    {
        $tenant = Tenant::factory()->create();

        $this->getJson("/api/v1/tenants/{$tenant->id}/units")->assertUnauthorized();
    }
}
