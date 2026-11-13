<?php

namespace Tests\Feature;

use App\Actions\Search\GlobalSearchAction;
use App\Models\Client;
use App\Models\ClientSeller;
use App\Models\CommercialOrder;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function member(Tenant $tenant, string $role = 'OWNER'): TenantUser
    {
        return TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => User::factory()->create()->id,
            'role' => $role,
            'status' => 'ACTIVE',
            'joined_at' => now(),
        ]);
    }

    private function client(Tenant $tenant, TenantUser $seller, string $name): Client
    {
        $client = Client::query()->create([
            'tenant_id' => $tenant->id,
            'person_type' => 'PF',
            'document' => (string) random_int(10000000000, 99999999999),
            'company_name' => $name,
            'is_active' => true,
        ]);
        ClientSeller::query()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'tenant_user_id' => $seller->id,
            'started_at' => now(),
        ]);
        return $client;
    }

    public function test_search_returns_only_records_from_active_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->member($tenant);
        $other = Tenant::factory()->create();
        $otherOwner = $this->member($other);

        $ownClient = $this->client($tenant, $owner, 'BuscaGlobal Cliente');
        $foreignClient = $this->client($other, $otherOwner, 'BuscaGlobal Cliente');

        $results = app(GlobalSearchAction::class)->execute($tenant, $owner->user, 'BuscaGlobal');

        $ids = array_column($results['clients'], 'id');
        $this->assertContains($ownClient->id, $ids);
        $this->assertNotContains($foreignClient->id, $ids);
        $this->assertSame(['clients', 'opportunities', 'orders'], array_keys($results));
    }

    public function test_consultant_cannot_find_clients_outside_portfolio(): void
    {
        $tenant = Tenant::factory()->create();
        $first = $this->member($tenant, 'CONSULTANT');
        $second = $this->member($tenant, 'CONSULTANT');
        $own = $this->client($tenant, $first, 'BuscaGlobal Minha Carteira');
        $hidden = $this->client($tenant, $second, 'BuscaGlobal Outra Carteira');

        $results = app(GlobalSearchAction::class)->execute($tenant, $first->user, 'BuscaGlobal');
        $ids = array_column($results['clients'], 'id');

        $this->assertContains($own->id, $ids);
        $this->assertNotContains($hidden->id, $ids);
    }

    public function test_search_endpoint_validates_minimum_query_length(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->member($tenant);

        $this->actingAs($owner->user)
            ->getJson("/api/v1/tenants/{$tenant->id}/global-search?q=a")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');
    }

    public function test_search_endpoint_requires_authentication(): void
    {
        $tenant = Tenant::factory()->create();

        $this->getJson("/api/v1/tenants/{$tenant->id}/global-search?q=cliente")
            ->assertUnauthorized();
    }
}
