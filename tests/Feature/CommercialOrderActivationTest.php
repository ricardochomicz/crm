<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommercialOrderActivationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function authenticatedTenant(): Tenant
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $tenant->users()->attach($user, ['role' => 'OWNER', 'status' => 'ACTIVE']);
        Subscription::factory()->for($tenant)->trialing()->create();
        $this->actingAs($user);

        return $tenant;
    }

    public function test_activation_requires_selected_items_and_date(): void
    {
        $tenant = $this->authenticatedTenant();
        $orderId = (string) Str::uuid();

        $this->postJson("/api/v1/tenants/{$tenant->id}/commercial-orders/{$orderId}/activate-items", [])
            ->assertUnprocessable()->assertInvalid(['item_ids', 'activated_at']);
    }

    public function test_activation_rejects_duplicate_item_ids(): void
    {
        $tenant = $this->authenticatedTenant();
        $id = (string) Str::uuid();

        $this->postJson("/api/v1/tenants/{$tenant->id}/commercial-orders/{$id}/activate-items", [
            'item_ids' => [$id, $id],
            'activated_at' => '2026-11-01',
        ])->assertUnprocessable()->assertInvalid(['item_ids.1']);
    }

    public function test_activation_does_not_accept_future_dates(): void
    {
        $tenant = $this->authenticatedTenant();
        $id = (string) Str::uuid();

        $this->postJson("/api/v1/tenants/{$tenant->id}/commercial-orders/{$id}/activate-items", [
            'item_ids' => [$id],
            'activated_at' => now()->addDay()->toDateString(),
        ])->assertUnprocessable()->assertInvalid(['activated_at']);
    }

    public function test_activation_does_not_find_orders_outside_tenant(): void
    {
        $tenant = $this->authenticatedTenant();
        $orderId = (string) Str::uuid();

        $this->postJson("/api/v1/tenants/{$tenant->id}/commercial-orders/{$orderId}/activate-items", [
            'item_ids' => [(string) Str::uuid()],
            'activated_at' => now()->toDateString(),
        ])->assertNotFound();
    }
}
