<?php

namespace Tests\Feature;

use App\Actions\Commercial\ScopeVisibleCommercialOrdersAction;
use App\Actions\Tenant\SeedTenantRolesAction;
use App\Models\CommercialOrder;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommercialOrderVisibilityScopeTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_consultant_scope_excludes_coworker_and_other_tenant_orders(): void
    {
        $tenant = Tenant::factory()->create();
        Subscription::factory()->for($tenant)->trialing()->create(['user_limit' => 30]);
        app(SeedTenantRolesAction::class)->execute($tenant);
        $role = $tenant->roles()->where('code', 'CONSULTANT')->firstOrFail();
        $actor = $this->member($tenant, $role);
        $colleague = $this->member($tenant, $role);
        $otherTenant = Tenant::factory()->create();
        Subscription::factory()->for($otherTenant)->trialing()->create(['user_limit' => 30]);
        app(SeedTenantRolesAction::class)->execute($otherTenant);
        $other = $this->member($otherTenant, $otherTenant->roles()->where('code', 'CONSULTANT')->firstOrFail());

        $scope = app(ScopeVisibleCommercialOrdersAction::class);
        self::assertSame([$actor->id], $scope->membershipIds($tenant, $actor->user));
        $query = $scope->apply(CommercialOrder::query(), $tenant, $actor->user);
        $sql = $query->toSql();
        $bindings = $query->getBindings();

        self::assertStringContainsString('tenant_id', $sql);
        self::assertStringContainsString('tenant_user_id', $sql);
        self::assertContains($tenant->id, $bindings);
        self::assertContains($actor->id, $bindings);
        self::assertNotContains($colleague->id, $bindings);
        self::assertNotContains($other->id, $bindings);
    }

    public function test_supervisor_scope_includes_own_and_supervised_team_but_not_other_teams(): void
    {
        $tenant = Tenant::factory()->create();
        Subscription::factory()->for($tenant)->trialing()->create(['user_limit' => 30]);
        app(SeedTenantRolesAction::class)->execute($tenant);
        $role = $tenant->roles()->where('code', 'CONSULTANT')->firstOrFail();

        $supervisor = $this->member($tenant, $role);
        $supervisedTeam = Team::factory()->supervisedBy($supervisor)->create();
        $otherTeam = Team::factory()->create(['tenant_id' => $tenant->id]);
        $teamMember = $this->member($tenant, $role);
        $teamMember->update(['team_id' => $supervisedTeam->id]);
        $otherMember = $this->member($tenant, $role);
        $otherMember->update(['team_id' => $otherTeam->id]);

        $ids = app(ScopeVisibleCommercialOrdersAction::class)->membershipIds($tenant, $supervisor->user);

        self::assertEqualsCanonicalizing([$supervisor->id, $teamMember->id], $ids);
        self::assertNotContains($otherMember->id, $ids);
    }

    public function test_owner_scope_is_unrestricted_only_within_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        Subscription::factory()->for($tenant)->trialing()->create(['user_limit' => 30]);
        app(SeedTenantRolesAction::class)->execute($tenant);
        $owner = $this->member($tenant, $tenant->roles()->where('code', 'OWNER')->firstOrFail());

        $scope = app(ScopeVisibleCommercialOrdersAction::class);
        self::assertNull($scope->membershipIds($tenant, $owner->user));

        $query = $scope->apply(CommercialOrder::query(), $tenant, $owner->user);
        self::assertStringContainsString('tenant_id', $query->toSql());
        self::assertContains($tenant->id, $query->getBindings());
    }

    private function member(Tenant $tenant, \App\Models\TenantRole $role): TenantUser
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
}
