<?php

namespace Tests\Feature;

use App\Actions\Tenant\SeedTenantRolesAction;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommercialOrderOptionsAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_read_only_order_user_cannot_enumerate_other_members(): void
    {
        $tenant = Tenant::factory()->create();
        Subscription::factory()->for($tenant)->trialing()->create(['user_limit' => 30]);
        app(SeedTenantRolesAction::class)->execute($tenant);
        $role = $tenant->roles()->where('code', 'CONSULTANT')->firstOrFail();
        $role->update(['permissions' => ['orders.view']]);
        $viewer = TenantUser::query()->create([
            'tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id,
            'role' => $role->legacy_role, 'tenant_role_id' => $role->id,
            'status' => 'ACTIVE', 'joined_at' => now(),
        ]);
        $other = TenantUser::query()->create([
            'tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id,
            'role' => $role->legacy_role, 'tenant_role_id' => $role->id,
            'status' => 'ACTIVE', 'joined_at' => now(),
        ]);

        $this->actingAs($viewer->user)
            ->getJson("/api/v1/tenants/{$tenant->id}/commercial-orders/options")
            ->assertOk()
            ->assertJsonCount(1, 'data.members')
            ->assertJsonPath('data.members.0.id', $viewer->id)
            ->assertJsonCount(0, 'data.externals');

        $this->assertNotSame($viewer->id, $other->id);
    }

    public function test_user_without_orders_view_cannot_read_order_options(): void
    {
        $tenant = Tenant::factory()->create();
        Subscription::factory()->for($tenant)->trialing()->create(['user_limit' => 30]);
        app(SeedTenantRolesAction::class)->execute($tenant);
        $role = $tenant->roles()->where('code', 'CONSULTANT')->firstOrFail();
        $role->update(['permissions' => []]);
        $viewer = TenantUser::query()->create([
            'tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id,
            'role' => $role->legacy_role, 'tenant_role_id' => $role->id,
            'status' => 'ACTIVE', 'joined_at' => now(),
        ]);

        $this->actingAs($viewer->user)
            ->getJson("/api/v1/tenants/{$tenant->id}/commercial-orders/options")
            ->assertForbidden();
    }
}
