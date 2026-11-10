<?php

namespace Tests\Feature;

use App\Actions\Tenant\SeedTenantRolesAction;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantRole;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class TenantRoleAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function company(): Tenant
    {
        $tenant = Tenant::factory()->create();
        Subscription::factory()->for($tenant)->trialing()->create(['user_limit' => 30]);
        app(SeedTenantRolesAction::class)->execute($tenant);

        return $tenant;
    }

    /** @param list<string> $permissions */
    private function role(Tenant $tenant, string $code, array $permissions = []): TenantRole
    {
        $role = $tenant->roles()->where('code', $code)->firstOrFail();
        $role->update(['permissions' => $permissions]);

        return $role->refresh();
    }

    private function member(Tenant $tenant, TenantRole $role): TenantUser
    {
        return TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => User::factory()->create()->id,
            'role' => $role->legacy_role,
            'tenant_role_id' => $role->id,
            'status' => 'ACTIVE',
            'joined_at' => now(),
        ]);
    }

    public function test_consultant_is_listed_and_can_replace_telemarketing_with_independent_permissions(): void
    {
        $tenant = $this->company();
        $owner = $this->member($tenant, $this->role($tenant, 'OWNER'));
        $telemarketing = $this->role($tenant, 'TELEMARKETING');
        $member = $this->member($tenant, $telemarketing);
        $consultant = $tenant->roles()->where('code', 'CONSULTANT')->firstOrFail();
        $this->actingAs($owner->user);

        $this->getJson("/api/v1/tenants/{$tenant->id}/roles")
            ->assertOk()->assertJsonFragment(['id' => $consultant->id, 'name' => 'Consultor', 'can_assign' => true])
            ->assertJsonFragment(['id' => $telemarketing->id, 'name' => 'Telemarketing']);
        $this->patchJson("/api/v1/tenants/{$tenant->id}/roles/{$consultant->id}", [
            'name' => 'Consultor', 'permissions' => ['clients.view'], 'version' => 1,
        ])->assertOk()->assertJsonPath('data.permissions', ['clients.view']);
        $this->patchJson("/api/v1/tenants/{$tenant->id}/members/{$member->id}/role", [
            'role' => 'CONSULTANT', 'tenant_role_id' => $consultant->id,
        ])->assertNoContent();

        $this->assertDatabaseHas('tenant_users', ['id' => $member->id, 'tenant_role_id' => $consultant->id]);
        $this->assertSame([], $telemarketing->fresh()->permissions);
        $this->assertTrue($member->user->can('viewClients', $tenant));
    }

    public function test_consultant_migration_backfills_existing_companies_without_changing_assignments_or_customizations(): void
    {
        $tenant = $this->company();
        $tenant->roles()->where('code', 'CONSULTANT')->delete();
        $telemarketing = $this->role($tenant, 'TELEMARKETING');
        $member = $this->member($tenant, $telemarketing);
        $otherTenant = $this->company();
        $customized = $this->role($otherTenant, 'CONSULTANT', ['clients.view']);
        $customized->update(['name' => 'Consultor externo', 'is_active' => false]);
        $migration = require database_path('migrations/2026_11_10_191313_add_consultant_to_tenant_roles.php');

        $migration->up();
        $migration->up();

        $this->assertDatabaseHas('tenant_roles', ['tenant_id' => $tenant->id, 'code' => 'CONSULTANT', 'name' => 'Consultor', 'is_active' => true]);
        $this->assertSame(1, $tenant->roles()->where('code', 'CONSULTANT')->count());
        $this->assertSame($telemarketing->id, $member->fresh()->tenant_role_id);
        $this->assertDatabaseHas('tenant_roles', ['id' => $customized->id, 'name' => 'Consultor externo', 'is_active' => false]);
        $this->assertSame(['clients.view'], $customized->fresh()->permissions);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_role_assignment_cannot_grant_missing_permissions_with_or_without_explicit_id(bool $explicit): void
    {
        $tenant = $this->company();
        $actor = $this->member($tenant, $this->role($tenant, 'MANAGER', ['members.view', 'members.role']));
        $target = $this->member($tenant, $this->role($tenant, 'TELEMARKETING'));
        $powerful = $this->role($tenant, 'BACKOFFICE', ['commissions.view']);
        $payload = ['role' => 'ASSISTANT'];
        if ($explicit) {
            $payload['tenant_role_id'] = $powerful->id;
        }

        $this->actingAs($actor->user)->patchJson("/api/v1/tenants/{$tenant->id}/members/{$target->id}/role", $payload)->assertForbidden();
        $this->assertDatabaseHas('tenant_users', ['id' => $target->id, 'tenant_role_id' => $target->tenant_role_id]);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_invitation_cannot_grant_missing_permissions_with_or_without_explicit_id(bool $explicit): void
    {
        Notification::fake();
        $tenant = $this->company();
        $actor = $this->member($tenant, $this->role($tenant, 'MANAGER', ['invitations.view', 'invitations.create']));
        $powerful = $this->role($tenant, 'BACKOFFICE', ['commissions.view']);
        $payload = ['email' => 'invited@example.com', 'role' => 'ASSISTANT'];
        if ($explicit) {
            $payload['tenant_role_id'] = $powerful->id;
        }

        $this->actingAs($actor->user)->postJson("/api/v1/tenants/{$tenant->id}/invitations", $payload)->assertForbidden();
        $this->assertDatabaseCount('tenant_invitations', 0);
        Notification::assertNothingSent();
    }

    #[TestWith(['status'])]
    #[TestWith(['role'])]
    #[TestWith(['remove'])]
    public function test_member_management_compares_actual_custom_permissions(string $operation): void
    {
        $tenant = $this->company();
        $actor = $this->member($tenant, $this->role($tenant, 'MANAGER', ['members.view', 'members.status', 'members.role', 'members.remove']));
        $target = $this->member($tenant, $this->role($tenant, 'FINANCE', ['commissions.view']));
        $url = "/api/v1/tenants/{$tenant->id}/members/{$target->id}";
        $this->actingAs($actor->user);

        $response = match ($operation) {
            'status' => $this->patchJson($url.'/status', ['status' => 'SUSPENDED']),
            'role' => $this->patchJson($url.'/role', ['role' => 'CONSULTANT']),
            'remove' => $this->deleteJson($url),
        };
        $response->assertForbidden();
        $this->assertDatabaseHas('tenant_users', ['id' => $target->id, 'status' => 'ACTIVE', 'tenant_role_id' => $target->tenant_role_id]);
        $this->getJson("/api/v1/tenants/{$tenant->id}/members")
            ->assertOk()->assertJsonFragment(['id' => $target->id, 'can_manage' => false]);
    }

    public function test_custom_permissions_allow_assigning_a_weaker_custom_role_despite_legacy_settings(): void
    {
        $tenant = $this->company();
        $actor = $this->member($tenant, $this->role($tenant, 'FINANCE', ['members.view', 'members.role', 'clients.view']));
        $target = $this->member($tenant, $this->role($tenant, 'BACKOFFICE'));
        $role = $this->role($tenant, 'TELEMARKETING', ['clients.view']);

        $this->actingAs($actor->user)->patchJson("/api/v1/tenants/{$tenant->id}/members/{$target->id}/role", [
            'role' => 'CONSULTANT', 'tenant_role_id' => $role->id,
        ])->assertNoContent();
        $this->assertDatabaseHas('tenant_users', ['id' => $target->id, 'tenant_role_id' => $role->id, 'role' => 'CONSULTANT']);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_direct_registration_sets_the_actual_company_role(bool $explicit): void
    {
        $tenant = $this->company();
        $owner = $this->member($tenant, $this->role($tenant, 'OWNER'));
        $role = $this->role($tenant, $explicit ? 'FINANCE' : 'BACKOFFICE', ['clients.view']);
        $data = ['name' => 'Nova pessoa', 'email' => 'new@example.com', 'role' => 'ASSISTANT'];
        if ($explicit) {
            $data['tenant_role_id'] = $role->id;
        }
        $this->actingAs($owner->user)->postJson("/api/v1/tenants/{$tenant->id}/members", $data)->assertCreated();
        $user = User::query()->where('email', 'new@example.com')->firstOrFail();
        $this->assertDatabaseHas('tenant_users', ['user_id' => $user->id, 'tenant_role_id' => $role->id]);
        $this->assertTrue($user->can('viewClients', $tenant));
        $role->update(['permissions' => []]);
        $this->assertFalse($user->can('viewClients', $tenant));
    }

    public function test_default_inactive_role_cannot_be_assigned(): void
    {
        $tenant = $this->company();
        $owner = $this->member($tenant, $this->role($tenant, 'OWNER'));
        $target = $this->member($tenant, $this->role($tenant, 'TELEMARKETING'));
        $this->role($tenant, 'BACKOFFICE')->update(['is_active' => false]);
        $this->actingAs($owner->user)->patchJson("/api/v1/tenants/{$tenant->id}/members/{$target->id}/role", ['role' => 'ASSISTANT'])->assertUnprocessable();
        $this->assertDatabaseHas('tenant_users', ['id' => $target->id, 'role' => 'CONSULTANT']);
    }

    public function test_owner_self_and_cross_tenant_members_cannot_be_managed(): void
    {
        $tenant = $this->company();
        $owner = $this->member($tenant, $this->role($tenant, 'OWNER'));
        $otherTenant = $this->company();
        $other = $this->member($otherTenant, $this->role($otherTenant, 'BACKOFFICE'));
        $this->actingAs($owner->user);
        $this->patchJson("/api/v1/tenants/{$tenant->id}/members/{$owner->id}/status", ['status' => 'SUSPENDED'])->assertForbidden();
        $this->patchJson("/api/v1/tenants/{$tenant->id}/members/{$other->id}/status", ['status' => 'SUSPENDED'])->assertNotFound();
        $this->assertDatabaseHas('tenant_users', ['id' => $owner->id, 'status' => 'ACTIVE']);
        $this->assertDatabaseHas('tenant_users', ['id' => $other->id, 'status' => 'ACTIVE']);
    }

    public function test_custom_role_edits_are_versioned_audited_and_stale_writes_are_rejected(): void
    {
        $tenant = $this->company();
        $owner = $this->member($tenant, $this->role($tenant, 'OWNER'));
        $role = $this->role($tenant, 'FINANCE', ['clients.view']);
        $url = "/api/v1/tenants/{$tenant->id}/roles/{$role->id}";
        $data = ['name' => 'Financeiro restrito', 'permissions' => [], 'version' => 1, 'is_active' => false];
        $this->actingAs($owner->user)->patchJson($url, $data)->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.permissions', []);
        $this->patchJson($url, $data + ['code' => 'CHANGED'])->assertConflict();
        $this->assertDatabaseCount('tenant_permission_changes', 1);
        $this->assertDatabaseHas('tenant_roles', ['id' => $role->id, 'version' => 2, 'name' => 'Financeiro restrito']);
        $this->getJson("/api/v1/tenants/{$tenant->id}/permission-changes?tenant_role_id={$role->id}")
            ->assertOk()->assertJsonPath('data.0.actor_name', $owner->user->name)
            ->assertJsonPath('data.0.previous_role_state.permissions', ['clients.view'])
            ->assertJsonPath('data.0.role_state.is_active', false)->assertJsonPath('data.0.version', 2);
    }

    public function test_owner_can_create_a_role_without_permissions_and_creation_is_audited(): void
    {
        $tenant = $this->company();
        $owner = $this->member($tenant, $this->role($tenant, 'OWNER'));
        $this->actingAs($owner->user)->postJson("/api/v1/tenants/{$tenant->id}/roles", ['name' => 'Sem acesso', 'permissions' => []])
            ->assertCreated()->assertJsonPath('data.version', 1)->assertJsonPath('data.permissions', []);
        $this->assertDatabaseHas('tenant_permission_changes', ['tenant_id' => $tenant->id, 'actor_user_id' => $owner->user_id, 'version' => 1]);
    }

    public function test_role_updates_enforce_owner_scope_dependencies_and_version(): void
    {
        $tenant = $this->company();
        $owner = $this->member($tenant, $this->role($tenant, 'OWNER'));
        $manager = $this->member($tenant, $this->role($tenant, 'MANAGER'));
        $role = $this->role($tenant, 'FINANCE');
        $url = "/api/v1/tenants/{$tenant->id}/roles/{$role->id}";
        $data = ['name' => 'Financeiro', 'permissions' => ['clients.update'], 'version' => 1];
        $this->patchJson($url, $data)->assertUnauthorized();
        $this->actingAs($manager->user)->patchJson($url, $data)->assertForbidden();
        $this->actingAs($owner->user)->patchJson($url, $data)->assertUnprocessable()->assertInvalid('permissions');
        $this->patchJson($url, ['name' => 'Financeiro', 'permissions' => []])->assertUnprocessable()->assertInvalid('version');
        $otherTenant = $this->company();
        $other = $this->role($otherTenant, 'FINANCE');
        $this->patchJson("/api/v1/tenants/{$tenant->id}/roles/{$other->id}", ['name' => 'Financeiro', 'permissions' => [], 'version' => 1])->assertNotFound();
        $this->assertDatabaseCount('tenant_permission_changes', 0);
    }

    public function test_role_list_and_active_access_expose_only_assignable_custom_roles(): void
    {
        $tenant = $this->company();
        $actor = $this->member($tenant, $this->role($tenant, 'MANAGER', ['members.view', 'clients.view']));
        $allowed = $this->role($tenant, 'FINANCE', ['clients.view']);
        $denied = $this->role($tenant, 'BACKOFFICE', ['commissions.view']);
        $this->actingAs($actor->user)->getJson("/api/v1/tenants/{$tenant->id}/roles")
            ->assertOk()->assertJsonFragment(['id' => $allowed->id, 'can_assign' => true])
            ->assertJsonFragment(['id' => $denied->id, 'can_assign' => false]);
        $this->getJson("/api/v1/tenants/{$tenant->id}")->assertOk()
            ->assertJsonPath('data.access.tenant_role_id', $actor->tenant_role_id)
            ->assertJsonPath('data.access.role_name', 'Gerente')
            ->assertJsonPath('data.access.assignable_roles', ['CONSULTANT', 'ASSISTANT']);
    }

    public function test_resend_rechecks_the_invitation_custom_role_permissions(): void
    {
        Notification::fake();
        $tenant = $this->company();
        $actor = $this->member($tenant, $this->role($tenant, 'MANAGER', ['invitations.view', 'invitations.resend']));
        $role = $this->role($tenant, 'FINANCE', ['commissions.view']);
        $invitation = TenantInvitation::factory()->for($tenant)->create([
            'email' => 'invited@example.com', 'role' => 'ASSISTANT', 'tenant_role_id' => $role->id,
            'invited_by_user_id' => $actor->user_id, 'status' => 'PENDING',
            'token_hash' => hash('sha256', 'previous-token'), 'expires_at' => now()->addDay(),
        ]);
        $this->actingAs($actor->user)->postJson("/api/v1/tenants/{$tenant->id}/invitations/{$invitation->id}/resend")->assertForbidden();
        $this->assertDatabaseHas('tenant_invitations', ['id' => $invitation->id, 'token_hash' => hash('sha256', 'previous-token')]);
        Notification::assertNothingSent();
    }

    public function test_suspended_member_cannot_use_custom_role_permissions(): void
    {
        $tenant = $this->company();
        $actor = $this->member($tenant, $this->role($tenant, 'MANAGER', ['members.view']));
        $actor->update(['status' => 'SUSPENDED']);
        $this->actingAs($actor->user)->getJson("/api/v1/tenants/{$tenant->id}/members")->assertNotFound();
        $this->assertFalse($actor->user->can('viewMembers', $tenant));
    }

    public function test_role_audit_is_isolated_by_tenant(): void
    {
        $tenant = $this->company();
        $owner = $this->member($tenant, $this->role($tenant, 'OWNER'));
        $role = $this->role($tenant, 'FINANCE');
        $this->actingAs($owner->user)->patchJson("/api/v1/tenants/{$tenant->id}/roles/{$role->id}", [
            'name' => 'Financeiro alterado', 'permissions' => [], 'version' => 1,
        ])->assertOk();
        $otherTenant = $this->company();
        $otherOwner = $this->member($otherTenant, $this->role($otherTenant, 'OWNER'));
        $this->actingAs($otherOwner->user)->getJson("/api/v1/tenants/{$otherTenant->id}/permission-changes?tenant_role_id={$role->id}")
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_legacy_assignment_migration_keeps_custom_roles_and_tenant_boundaries(): void
    {
        $tenant = $this->company();
        $otherTenant = $this->company();
        $custom = $this->member($tenant, $this->role($tenant, 'FINANCE'));
        $legacy = $this->member($tenant, $this->role($tenant, 'BACKOFFICE'));
        $legacy->update(['tenant_role_id' => null]);
        $other = $this->member($otherTenant, $this->role($otherTenant, 'BACKOFFICE'));
        $other->update(['tenant_role_id' => null]);
        $invitation = TenantInvitation::factory()->for($tenant)->create(['role' => 'ASSISTANT']);

        $migration = require database_path('migrations/2026_11_08_233219_assign_company_roles_to_legacy_memberships.php');
        $migration->up();
        $migration->up();

        $this->assertSame($custom->tenant_role_id, $custom->fresh()->tenant_role_id);
        $this->assertSame($this->role($tenant, 'BACKOFFICE')->id, $legacy->fresh()->tenant_role_id);
        $this->assertSame($this->role($otherTenant, 'BACKOFFICE')->id, $other->fresh()->tenant_role_id);
        $this->assertSame($legacy->fresh()->tenant_role_id, $invitation->fresh()->tenant_role_id);
    }

    #[TestWith(['commercial-goals'])]
    #[TestWith(['team-commissions'])]
    #[TestWith(['operator-classifications'])]
    public function test_commission_access_does_not_authorize_other_module_endpoints(string $module): void
    {
        $tenant = $this->company();
        $member = $this->member($tenant, $this->role($tenant, 'FINANCE', ['commissions.view', 'commissions.manage']));
        $this->actingAs($member->user)->getJson("/api/v1/tenants/{$tenant->id}/{$module}")->assertForbidden();
    }

    public function test_authorized_invitation_preserves_the_selected_custom_role(): void
    {
        config(['app.frontend_url' => 'https://app.example.test']);
        Notification::fake();
        $tenant = $this->company();
        $actor = $this->member($tenant, $this->role($tenant, 'MANAGER', ['invitations.view', 'invitations.create', 'clients.view']));
        $role = $this->role($tenant, 'FINANCE', ['clients.view']);
        $this->actingAs($actor->user)->postJson("/api/v1/tenants/{$tenant->id}/invitations", [
            'email' => 'invited@example.com', 'role' => 'ASSISTANT', 'tenant_role_id' => $role->id,
        ])->assertCreated();
        $this->assertDatabaseHas('tenant_invitations', ['tenant_id' => $tenant->id, 'tenant_role_id' => $role->id, 'email' => 'invited@example.com']);
        Notification::assertCount(1);
    }

    public function test_role_assignment_rejects_a_role_from_another_tenant(): void
    {
        $tenant = $this->company();
        $owner = $this->member($tenant, $this->role($tenant, 'OWNER'));
        $member = $this->member($tenant, $this->role($tenant, 'BACKOFFICE'));
        $otherTenant = $this->company();
        $otherRole = $this->role($otherTenant, 'FINANCE');
        $this->actingAs($owner->user)->patchJson("/api/v1/tenants/{$tenant->id}/members/{$member->id}/role", [
            'role' => 'ASSISTANT', 'tenant_role_id' => $otherRole->id,
        ])->assertUnprocessable()->assertInvalid('tenant_role_id');
        $this->assertDatabaseHas('tenant_users', ['id' => $member->id, 'tenant_role_id' => $member->tenant_role_id]);
    }
}
