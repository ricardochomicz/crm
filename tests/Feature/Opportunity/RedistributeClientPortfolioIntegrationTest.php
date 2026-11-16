<?php

namespace Tests\Feature\Opportunity;

use App\Actions\Client\RedistributeClientPortfolioAction;
use App\Models\Client;
use App\Models\ClientSeller;
use App\Models\Opportunity;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RedistributeClientPortfolioIntegrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_redistribution_moves_open_opportunities_and_preserves_closed_history(): void
    {
        [$tenant, $manager, $source, $destination, $clients] = $this->scenario(2);
        $open = $this->opportunity($tenant, $clients[0], $source, 'open');
        $closed = $this->opportunity($tenant, $clients[0], $source, 'won');
        $otherOpen = $this->opportunity($tenant, $clients[1], $source, 'open');

        $result = app(RedistributeClientPortfolioAction::class)->execute(
            $tenant, $manager->user_id, $this->payload($source, $destination, $clients)
        );

        self::assertSame(2, $result['client_count']);
        self::assertSame(2, $result['transferred_opportunities_count']);
        self::assertSame($destination->id, $open->fresh()->tenant_user_id);
        self::assertSame($destination->id, $otherOpen->fresh()->tenant_user_id);
        self::assertSame($source->id, $closed->fresh()->tenant_user_id);
        self::assertSame(2, DB::table('opportunity_ownership_transfers')
            ->where('tenant_id', $tenant->id)
            ->where('source_type', 'portfolio_redistribution')
            ->where('source_id', $result['id'])->count());
        self::assertSame(2, DB::table('client_portfolio_redistribution_items')
            ->where('redistribution_id', $result['id'])->count());
        foreach ($clients as $client) {
            $this->assertDatabaseHas('client_sellers', [
                'client_id' => $client->id,
                'tenant_user_id' => $destination->id,
                'ended_at' => null,
            ]);
            self::assertSame(1, ClientSeller::query()
                ->where('client_id', $client->id)->whereNull('ended_at')->count());
        }
    }

    public function test_one_invalid_opportunity_rolls_back_entire_redistribution(): void
    {
        [$tenant, $manager, $source, $destination, $clients] = $this->scenario(2);
        $valid = $this->opportunity($tenant, $clients[0], $source, 'open');
        $third = $this->member($tenant, 'CONSULTANT');
        $invalid = $this->opportunity($tenant, $clients[1], $third, 'open');

        try {
            app(RedistributeClientPortfolioAction::class)->execute(
                $tenant, $manager->user_id, $this->payload($source, $destination, $clients)
            );
            self::fail('A redistribuição deveria ser bloqueada.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('client_ids', $exception->errors());
        }

        self::assertSame($source->id, $valid->fresh()->tenant_user_id);
        self::assertSame($third->id, $invalid->fresh()->tenant_user_id);
        $this->assertDatabaseCount('opportunity_ownership_transfers', 0);
        $this->assertDatabaseCount('client_portfolio_redistributions', 0);
        $this->assertDatabaseCount('client_portfolio_redistribution_items', 0);
        foreach ($clients as $client) {
            $this->assertDatabaseHas('client_sellers', [
                'client_id' => $client->id, 'tenant_user_id' => $source->id, 'ended_at' => null,
            ]);
        }
    }

    public function test_redistribution_rejects_destination_from_another_tenant(): void
    {
        [$tenant, $manager, $source, , $clients] = $this->scenario(1);
        $foreignTenant = Tenant::factory()->create();
        $foreignDestination = $this->member($foreignTenant, 'CONSULTANT');

        $this->expectException(ValidationException::class);
        try {
            app(RedistributeClientPortfolioAction::class)->execute(
                $tenant, $manager->user_id, $this->payload($source, $foreignDestination, $clients)
            );
        } finally {
            $this->assertDatabaseCount('client_portfolio_redistributions', 0);
            $this->assertDatabaseCount('opportunity_ownership_transfers', 0);
        }
    }

    private function scenario(int $count): array
    {
        $tenant = Tenant::factory()->create();
        Subscription::factory()->for($tenant)->trialing()->create();
        $manager = $this->member($tenant, 'MANAGER');
        $source = $this->member($tenant, 'CONSULTANT');
        $destination = $this->member($tenant, 'CONSULTANT');
        $clients = [];
        for ($i = 0; $i < $count; $i++) {
            $client = Client::query()->create([
                'tenant_id' => $tenant->id, 'person_type' => 'PJ',
                'document' => sprintf('%014d', $i + 12345678000195),
                'company_name' => 'Cliente ' . $i,
            ]);
            ClientSeller::query()->create([
                'tenant_id' => $tenant->id, 'client_id' => $client->id,
                'tenant_user_id' => $source->id, 'assigned_by' => $manager->user_id,
                'started_at' => now(),
            ]);
            $clients[] = $client;
        }
        return [$tenant, $manager, $source, $destination, $clients];
    }

    private function payload(TenantUser $source, TenantUser $destination, array $clients): array
    {
        return [
            'source_membership_id' => $source->id,
            'client_ids' => array_map(fn (Client $client) => $client->id, $clients),
            'mode' => 'single',
            'reason' => 'Redistribuição de carteira autorizada',
            'destination_ids' => [$destination->id],
        ];
    }

    private function member(Tenant $tenant, string $role): TenantUser
    {
        return TenantUser::query()->create([
            'tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id,
            'role' => $role, 'status' => 'ACTIVE', 'joined_at' => now(),
        ]);
    }

    private function opportunity(Tenant $tenant, Client $client, TenantUser $owner, string $status): Opportunity
    {
        return Opportunity::query()->create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id,
            'tenant_user_id' => $owner->id, 'code' => (string) Str::uuid(),
            'title' => 'Oportunidade de integração', 'order_type' => 'NEW', 'status' => $status,
        ]);
    }
}
