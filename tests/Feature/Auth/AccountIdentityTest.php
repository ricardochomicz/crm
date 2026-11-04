<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AccountIdentityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_regular_account_cannot_access_platform_administration(): void
    {
        $user = User::factory()->create(['is_platform_admin' => false]);
        $this->actingAs($user)->getJson('/api/v1/me')
            ->assertOk()->assertJsonPath('data.is_platform_admin', false);
        $this->getJson('/api/v1/admin/tenants')->assertForbidden();
    }

    public function test_platform_admin_is_identified_and_can_list_companies(): void
    {
        $user = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($user)->getJson('/api/v1/me')
            ->assertOk()->assertJsonPath('data.is_platform_admin', true);
        $this->getJson('/api/v1/admin/tenants')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_guest_cannot_read_account_identity(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }
}
