<?php

namespace Tests\Feature\Opportunity;

use App\Actions\Client\TransferOpenClientOpportunitiesAction;
use App\Models\Client;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TransferOpenClientOpportunitiesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_moves_only_open_opportunities_and_records_audit(): void
    {
        [$tenant, $client, $old, $new] = $this->scenario();
        $open = $this->opportunity($tenant, $client, $old, 'open');
        $closed = $this->opportunity($tenant, $client, $old, 'lost');

        $count = DB::transaction(fn () => app(TransferOpenClientOpportunitiesAction::class)->execute(
            $tenant->id, $client->id, $old->id, $new->id, $new->user_id,
            'client_transfer', (string) Str::uuid(), 'Transferência aprovada'
        ));

        self::assertSame(1, $count);
        self::assertSame($new->id, $open->fresh()->tenant_user_id);
        self::assertSame($old->id, $closed->fresh()->tenant_user_id);
        $this->assertDatabaseHas('opportunity_ownership_transfers', [
            'opportunity_id' => $open->id,
            'from_tenant_user_id' => $old->id,
            'to_tenant_user_id' => $new->id,
            'actor_user_id' => $new->user_id,
            'reason' => 'Transferência aprovada',
        ]);
    }

    public function test_rejects_mismatched_owner_without_mutating_opportunities(): void
    {
        [$tenant, $client, $old, $new] = $this->scenario();
        $third = $this->member($tenant);
        $open = $this->opportunity($tenant, $client, $third, 'open');

        try {
            DB::transaction(fn () => app(TransferOpenClientOpportunitiesAction::class)->execute(
                $tenant->id, $client->id, $old->id, $new->id, $new->user_id,
                'client_transfer', (string) Str::uuid(), 'Transferência aprovada'
            ));
            self::fail('Deveria impedir a transferência de oportunidade de terceiro.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('client_ids', $exception->errors());
        }

        self::assertSame($third->id, $open->fresh()->tenant_user_id);
        $this->assertDatabaseCount('opportunity_ownership_transfers', 0);
    }

    public function test_transfer_is_rolled_back_when_surrounding_transaction_fails(): void
    {
        [$tenant, $client, $old, $new] = $this->scenario();
        $open = $this->opportunity($tenant, $client, $old, 'open');

        try {
            DB::transaction(function () use ($tenant, $client, $old, $new): void {
                app(TransferOpenClientOpportunitiesAction::class)->execute(
                    $tenant->id, $client->id, $old->id, $new->id, $new->user_id,
                    'portfolio_redistribution', (string) Str::uuid(), 'Teste de rollback'
                );
                throw new \RuntimeException('Falha simulada após transferência.');
            });
            self::fail('A transação deveria falhar.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Falha simulada após transferência.', $exception->getMessage());
        }

        self::assertSame($old->id, $open->fresh()->tenant_user_id);
        $this->assertDatabaseCount('opportunity_ownership_transfers', 0);
    }

    public function test_transfer_does_not_touch_another_tenant(): void
    {
        [$tenant, $client, $old, $new] = $this->scenario();
        $foreignTenant = Tenant::factory()->create();
        $foreignClient = Client::query()->create([
            'tenant_id' => $foreignTenant->id, 'person_type' => 'PJ',
            'document' => '12345678000196', 'company_name' => 'Cliente externo',
        ]);
        $foreignOwner = $this->member($foreignTenant);
        $foreignOpportunity = $this->opportunity($foreignTenant, $foreignClient, $foreignOwner, 'open');

        $count = DB::transaction(fn () => app(TransferOpenClientOpportunitiesAction::class)->execute(
            $tenant->id, $client->id, $old->id, $new->id, $new->user_id,
            'client_transfer', (string) Str::uuid(), 'Transferência restrita'
        ));

        self::assertSame(0, $count);
        self::assertSame($foreignOwner->id, $foreignOpportunity->fresh()->tenant_user_id);
        $this->assertDatabaseCount('opportunity_ownership_transfers', 0);
    }

    private function scenario(): array
    {
        $tenant = Tenant::factory()->create();
        $client = Client::query()->create([
            'tenant_id' => $tenant->id, 'person_type' => 'PJ',
            'document' => '12345678000195', 'company_name' => 'Cliente teste',
        ]);

        return [$tenant, $client, $this->member($tenant), $this->member($tenant)];
    }

    private function member(Tenant $tenant): TenantUser
    {
        return TenantUser::query()->create([
            'tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id,
            'role' => 'CONSULTANT', 'status' => 'ACTIVE', 'joined_at' => now(),
        ]);
    }

    private function opportunity(Tenant $tenant, Client $client, TenantUser $owner, string $status): Opportunity
    {
        return Opportunity::query()->create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id,
            'tenant_user_id' => $owner->id, 'code' => (string) Str::uuid(),
            'title' => 'Oportunidade teste', 'order_type' => 'NEW',
            'status' => $status,
        ]);
    }
}
