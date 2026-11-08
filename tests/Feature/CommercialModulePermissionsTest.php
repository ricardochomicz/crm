<?php

namespace Tests\Feature;

use App\Enums\TenantPermission;
use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommercialModulePermissionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_catalog_contains_independent_commercial_modules(): void
    {
        $catalog = collect(TenantPermission::catalog())->keyBy('key');

        foreach ([
            'commercial-goals.view', 'commercial-goals.manage',
            'team-commissions.view', 'team-commissions.manage',
            'operator-classifications.view', 'operator-classifications.manage',
        ] as $key) {
            $this->assertTrue($catalog->has($key), $key);
            $this->assertContains($key, TenantPermission::keys());
        }
    }

    public function test_custom_role_can_view_goals_without_managing_them(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $role = TenantRole::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'SALES_SUPERVISOR',
            'name' => 'Supervisor de Vendas',
            'legacy_role' => 'CONSULTANT',
            'permissions' => ['commercial-goals.view'],
            'is_system' => false,
            'is_active' => true,
        ]);
        TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'CONSULTANT',
            'tenant_role_id' => $role->id,
            'status' => 'ACTIVE',
            'joined_at' => now(),
        ]);

        $this->assertTrue($user->can('viewCommercialGoals', $tenant));
        $this->assertFalse($user->can('manageCommercialGoals', $tenant));
        $this->assertFalse($user->can('viewTeamCommissions', $tenant));
        $this->assertFalse($user->can('manageOperatorClassifications', $tenant));
    }

    public function test_commission_permissions_do_not_grant_other_commercial_modules(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $role = TenantRole::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'FINANCE',
            'name' => 'Financeiro',
            'legacy_role' => 'CONSULTANT',
            'permissions' => ['commissions.view', 'commissions.manage'],
            'is_system' => false,
            'is_active' => true,
        ]);
        TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'CONSULTANT',
            'tenant_role_id' => $role->id,
            'status' => 'ACTIVE',
            'joined_at' => now(),
        ]);

        $this->assertFalse($user->can('viewCommercialGoals', $tenant));
        $this->assertFalse($user->can('manageCommercialGoals', $tenant));
        $this->assertFalse($user->can('viewTeamCommissions', $tenant));
        $this->assertFalse($user->can('manageTeamCommissions', $tenant));
    }
}
