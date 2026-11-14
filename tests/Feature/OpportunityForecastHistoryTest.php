<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OpportunityForecastHistoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_cannot_read_forecast_history(): void
    {
        $tenant = Tenant::factory()->create();

        $this->getJson("/api/v1/tenants/{$tenant->id}/opportunities/".Str::uuid()."/forecast-history")
            ->assertUnauthorized();
    }

    public function test_member_cannot_read_forecast_history_of_another_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $tenant->users()->attach($user, ['role' => 'OWNER', 'status' => 'ACTIVE']);
        Subscription::factory()->for($tenant)->trialing()->create();

        $this->actingAs($user)
            ->getJson("/api/v1/tenants/{$otherTenant->id}/opportunities/".Str::uuid()."/forecast-history")
            ->assertNotFound();
    }

    public function test_authorized_member_gets_not_found_for_unknown_opportunity(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $tenant->users()->attach($user, ['role' => 'OWNER', 'status' => 'ACTIVE']);
        Subscription::factory()->for($tenant)->trialing()->create();

        $this->actingAs($user)
            ->getJson("/api/v1/tenants/{$tenant->id}/opportunities/".Str::uuid()."/forecast-history")
            ->assertNotFound();
    }
}
