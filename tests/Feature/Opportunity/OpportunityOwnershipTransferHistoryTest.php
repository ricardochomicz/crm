<?php

namespace Tests\Feature\Opportunity;

use App\Http\Controllers\Client\OpportunityOwnershipTransferHistoryController;
use App\Models\Client;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OpportunityOwnershipTransferHistoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_manager_can_filter_and_paginate_history_without_cross_tenant_records(): void
    {
        $tenant = Tenant::factory()->create();
        $manager = $this->member($tenant, 'MANAGER');
        $source = $this->member($tenant, 'CONSULTANT');
        $destination = $this->member($tenant, 'CONSULTANT');
        $client = $this->client($tenant);
        $otherClient = $this->client($tenant);
        $first = $this->record($tenant, $client, $source, $destination, $manager);
        $second = $this->record($tenant, $client, $source, $destination, $manager);
        $this->record($tenant, $otherClient, $source, $destination, $manager);

        $foreignTenant = Tenant::factory()->create();
        $foreignManager = $this->member($foreignTenant, 'MANAGER');
        $foreignSource = $this->member($foreignTenant, 'CONSULTANT');
        $foreignDestination = $this->member($foreignTenant, 'CONSULTANT');
        $this->record($foreignTenant, $this->client($foreignTenant), $foreignSource, $foreignDestination, $foreignManager);

        $response = $this->history($tenant, $manager, ['client_id' => $client->id, 'per_page' => 1, 'page' => 1]);
        self::assertSame(200, $response->status());
        $payload = $response->getData(true);
        self::assertSame(2, $payload['meta']['total']);
        self::assertSame(2, $payload['meta']['last_page']);
        self::assertCount(1, $payload['data']);
        self::assertSame($client->id, $payload['data'][0]['client_id']);
        self::assertSame($source->user->name, $payload['data'][0]['from_name']);
        self::assertSame($destination->user->name, $payload['data'][0]['to_name']);
        self::assertSame($manager->user->name, $payload['data'][0]['actor_name']);

        $next = $this->history($tenant, $manager, ['client_id' => $client->id, 'per_page' => 1, 'page' => 2])->getData(true);
        self::assertCount(1, $next['data']);
        self::assertEqualsCanonicalizing([$first, $second], [$payload['data'][0]['id'], $next['data'][0]['id']]);
    }

    public function test_consultant_cannot_read_history(): void
    {
        $tenant = Tenant::factory()->create();
        $consultant = $this->member($tenant, 'CONSULTANT');

        try {
            $this->history($tenant, $consultant);
            self::fail('O histórico gerencial não deve ser acessível a consultores.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_invalid_page_size_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $manager = $this->member($tenant, 'MANAGER');

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->history($tenant, $manager, ['per_page' => 101]);
    }

    private function history(Tenant $tenant, TenantUser $actor, array $params = []): \Illuminate\Http\JsonResponse
    {
        $request = Request::create('/history', 'GET', $params);
        $request->attributes->set('active_tenant', $tenant);
        $request->setUserResolver(fn () => $actor->user);

        return app(OpportunityOwnershipTransferHistoryController::class)($request, $tenant->id);
    }

    private function member(Tenant $tenant, string $role): TenantUser
    {
        return TenantUser::query()->create([
            'tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id,
            'role' => $role, 'status' => 'ACTIVE', 'joined_at' => now(),
        ]);
    }

    private function client(Tenant $tenant): Client
    {
        return Client::query()->create([
            'tenant_id' => $tenant->id, 'person_type' => 'PJ',
            'document' => (string) random_int(10000000000000, 99999999999999),
            'company_name' => 'Cliente do histórico',
        ]);
    }

    private function record(Tenant $tenant, Client $client, TenantUser $source, TenantUser $destination, TenantUser $actor): string
    {
        $opportunity = Opportunity::query()->create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id,
            'tenant_user_id' => $destination->id, 'code' => (string) Str::uuid(),
            'title' => 'Oportunidade transferida', 'order_type' => 'NEW', 'status' => 'open',
        ]);
        $id = (string) Str::uuid();
        DB::table('opportunity_ownership_transfers')->insert([
            'id' => $id, 'tenant_id' => $tenant->id, 'client_id' => $client->id,
            'opportunity_id' => $opportunity->id, 'from_tenant_user_id' => $source->id,
            'to_tenant_user_id' => $destination->id, 'actor_user_id' => $actor->user_id,
            'source_type' => 'client_transfer', 'source_id' => (string) Str::uuid(),
            'reason' => 'Transferência autorizada', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }
}
