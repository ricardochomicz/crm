<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientSeller;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ClientSellerUniquenessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_only_one_active_assignment_per_tenant_and_client_is_allowed(): void
    {
        $tenant = Tenant::factory()->create();
        $first = $this->member($tenant);
        $second = $this->member($tenant);
        $client = $this->client($tenant, '12345678000195');

        $this->assign($tenant, $client, $first);

        try {
            $this->assign($tenant, $client, $second);
            $this->fail('O banco permitiu dois responsáveis ativos para o mesmo cliente.');
        } catch (QueryException $exception) {
            $this->assertDatabaseCount('client_sellers', 1);
        }
    }

    public function test_historical_assignments_remain_available_after_transfer(): void
    {
        $tenant = Tenant::factory()->create();
        $first = $this->member($tenant);
        $second = $this->member($tenant);
        $client = $this->client($tenant, '12345678000195');

        $old = $this->assign($tenant, $client, $first);
        $old->update(['ended_at' => now()]);
        $this->assign($tenant, $client, $second);

        $this->assertDatabaseCount('client_sellers', 2);
        $this->assertSame(1, ClientSeller::query()->where('client_id', $client->id)->whereNull('ended_at')->count());
    }

    public function test_different_clients_can_have_active_assignments(): void
    {
        $tenant = Tenant::factory()->create();
        $seller = $this->member($tenant);

        $this->assign($tenant, $this->client($tenant, '12345678000195'), $seller);
        $this->assign($tenant, $this->client($tenant, '12345678000196'), $seller);

        $this->assertDatabaseCount('client_sellers', 2);
    }

    private function member(Tenant $tenant): TenantUser
    {
        return TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => User::factory()->create()->id,
            'role' => 'CONSULTANT',
            'status' => 'ACTIVE',
            'joined_at' => now(),
        ]);
    }

    private function client(Tenant $tenant, string $document): Client
    {
        return Client::query()->create([
            'tenant_id' => $tenant->id,
            'person_type' => 'PJ',
            'document' => $document,
            'company_name' => 'Cliente de carteira',
        ]);
    }

    private function assign(Tenant $tenant, Client $client, TenantUser $seller): ClientSeller
    {
        return ClientSeller::query()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'tenant_user_id' => $seller->id,
            'started_at' => now(),
        ]);
    }
}
