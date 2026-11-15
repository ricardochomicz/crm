<?php

namespace Tests\Feature;

use App\Actions\Commercial\ScopeVisibleCommercialOrdersAction;
use App\Actions\Tenant\SeedTenantRolesAction;
use App\Models\CommercialOrder;
use App\Models\Subscription;
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

        $ownOrder = CommercialOrder::factory()->create(['tenant_id' => $tenant->id, 'tenant_user_id' => $actor->id]);
        $colleagueOrder = CommercialOrder::factory()->create(['tenant_id' => $tenant->id, 'tenant_user_id' => $colleague->id]);
        $otherOrder = CommercialOrder::factory()->create(['tenant_id' => $otherTenant->id, 'tenant_user_id' => $other->id]);

        $ids = app(ScopeVisibleCommercialOrdersAction::class)
            ->apply(CommercialOrder::query(), $tenant, $actor->user)->pluck('id')->all();

        self::assertContains($ownOrder->id, $ids);
        self::assertNotContains($colleagueOrder->id, $ids);
        self::assertNotContains($otherOrder->id, $ids);
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
