<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TenantDetailsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function company(string $role = 'OWNER'): Tenant
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['name' => 'Empresa original', 'email' => 'contato@example.com']);
        $tenant->users()->attach($user, ['role' => $role, 'status' => 'ACTIVE']);
        Subscription::factory()->for($tenant)->trialing()->create();
        $this->actingAs($user);

        return $tenant;
    }

    public function test_member_can_read_company_details(): void
    {
        $tenant = $this->company('CONSULTANT');

        $this->getJson("/api/v1/tenants/{$tenant->id}")
            ->assertOk()->assertJsonPath('data.email', 'contato@example.com')
            ->assertJsonPath('data.document', '52998224725')
            ->assertJsonPath('data.timezone', 'America/Sao_Paulo');
    }

    public function test_owner_can_update_contact_details_without_changing_registration(): void
    {
        $tenant = $this->company();
        $data = ['name' => 'Novo nome', 'trade_name' => null, 'email' => 'novo@example.com', 'phone' => '11999999999', 'timezone' => 'America/Manaus'];

        $this->patchJson("/api/v1/tenants/{$tenant->id}", $data + ['document' => '123', 'type' => 'PJ'])->assertNoContent();

        $this->assertDatabaseHas('tenants', $data + ['id' => $tenant->id, 'document' => '52998224725', 'type' => 'PF']);
    }

    public function test_consultant_cannot_update_company(): void
    {
        $tenant = $this->company('CONSULTANT');

        $this->patchJson("/api/v1/tenants/{$tenant->id}", ['name' => 'Alterado'])->assertForbidden();

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'name' => 'Empresa original']);
    }

    public function test_other_company_is_not_accessible(): void
    {
        $this->company();
        $other = Tenant::factory()->create();

        $this->getJson("/api/v1/tenants/{$other->id}")->assertNotFound();
        $this->patchJson("/api/v1/tenants/{$other->id}", ['name' => 'Alterado'])->assertNotFound();
    }

    public function test_invalid_details_are_rejected_without_saving(): void
    {
        $tenant = $this->company();

        $this->patchJson("/api/v1/tenants/{$tenant->id}", ['name' => '', 'email' => 'invalid', 'timezone' => 'invalid'])
            ->assertUnprocessable()->assertInvalid(['name', 'email', 'timezone']);

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'name' => 'Empresa original']);
    }

    public function test_expired_trial_blocks_company_updates(): void
    {
        $tenant = $this->company();
        $tenant->subscription->update(['trial_ends_at' => now()->subDay()]);

        $response = $this->patchJson("/api/v1/tenants/{$tenant->id}", ['name' => 'Alterado']);
        $response->assertStatus(403);
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'name' => 'Empresa original']);
    }

    public function test_guest_cannot_read_or_update_company(): void
    {
        $tenant = Tenant::factory()->create();

        $this->getJson("/api/v1/tenants/{$tenant->id}")->assertUnauthorized();
        $this->patchJson("/api/v1/tenants/{$tenant->id}", ['name' => 'Alterado'])->assertUnauthorized();
    }
}
