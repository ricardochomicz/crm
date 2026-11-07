<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TenantMemberProfileEndpointTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_can_open_member_profile(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->tenants()->attach($tenant->id, ['role' => 'OWNER', 'status' => 'ACTIVE']);
        $member->tenants()->attach($tenant->id, ['role' => 'CONSULTANT', 'status' => 'ACTIVE']);
        $membership = TenantUser::query()->where('tenant_id', $tenant->id)
            ->where('user_id', $member->id)->firstOrFail();

        $this->actingAs($owner)
            ->getJson("/api/v1/tenants/{$tenant->id}/members/{$membership->id}/profile")
            ->assertOk()
            ->assertJsonPath('data.id', $membership->id)
            ->assertJsonPath('data.email', $member->email);
    }

    public function test_member_profile_from_another_tenant_is_not_found(): void
    {
        $first = Tenant::factory()->create();
        $second = Tenant::factory()->create();
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->tenants()->attach($first->id, ['role' => 'OWNER', 'status' => 'ACTIVE']);
        $member->tenants()->attach($second->id, ['role' => 'CONSULTANT', 'status' => 'ACTIVE']);
        $membership = TenantUser::query()->where('tenant_id', $second->id)
            ->where('user_id', $member->id)->firstOrFail();

        $this->actingAs($owner)
            ->getJson("/api/v1/tenants/{$first->id}/members/{$membership->id}/profile")
            ->assertNotFound();
    }
}
