<?php

namespace Tests\Feature\Opportunity;

use App\Actions\Opportunity\AssertClientPortfolioOwnershipAction;
use App\Models\Client;
use App\Models\ClientSeller;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClientPortfolioOwnershipTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_only_current_portfolio_owner_can_be_assigned_to_new_opportunity(): void
    {
        $tenant = Tenant::factory()->create();
        $seller = $this->member($tenant);
        $other = $this->member($tenant);
        $client = Client::query()->create([
            'tenant_id' => $tenant->id, 'person_type' => 'PJ',
            'document' => '12345678000195', 'company_name' => 'Cliente exclusivo',
        ]);
        ClientSeller::query()->create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id,
            'tenant_user_id' => $seller->id, 'assigned_by' => $seller->user_id,
            'started_at' => now(),
        ]);
        $check = app(AssertClientPortfolioOwnershipAction::class);

        DB::transaction(fn () => $check->execute($tenant->id, $client->id, $seller->id));

        try {
            DB::transaction(fn () => $check->execute($tenant->id, $client->id, $other->id));
            self::fail('Um consultor de outra carteira não deve poder negociar este cliente.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('client_id', $exception->errors());
        }
    }

    public function test_client_without_active_portfolio_owner_is_not_silently_assigned(): void
    {
        $tenant = Tenant::factory()->create();
        $seller = $this->member($tenant);
        $client = Client::query()->create([
            'tenant_id' => $tenant->id, 'person_type' => 'PJ',
            'document' => '12345678000195', 'company_name' => 'Cliente sem carteira',
        ]);

        $this->expectException(ValidationException::class);
        DB::transaction(fn () => app(AssertClientPortfolioOwnershipAction::class)
            ->execute($tenant->id, $client->id, $seller->id));
    }

    public function test_transfer_revokes_old_owner_and_authorizes_new_owner(): void
    {
        $tenant = Tenant::factory()->create();
        $previous = $this->member($tenant);
        $newOwner = $this->member($tenant);
        $client = Client::query()->create([
            'tenant_id' => $tenant->id, 'person_type' => 'PJ',
            'document' => '12345678000195', 'company_name' => 'Cliente transferido',
        ]);
        $assignment = ClientSeller::query()->create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id,
            'tenant_user_id' => $previous->id, 'assigned_by' => $previous->user_id,
            'started_at' => now()->subDay(),
        ]);
        $check = app(AssertClientPortfolioOwnershipAction::class);
        DB::transaction(function () use ($assignment, $tenant, $client, $newOwner): void {
            $assignment->update(['ended_at' => now()]);
            ClientSeller::query()->create([
                'tenant_id' => $tenant->id, 'client_id' => $client->id,
                'tenant_user_id' => $newOwner->id, 'assigned_by' => $newOwner->user_id,
                'started_at' => now(),
            ]);
        });

        DB::transaction(fn () => $check->execute($tenant->id, $client->id, $newOwner->id));

        try {
            DB::transaction(fn () => $check->execute($tenant->id, $client->id, $previous->id));
            self::fail('O antigo consultor não pode vender após a transferência.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('client_id', $exception->errors());
        }
    }

    private function member(Tenant $tenant): TenantUser
    {
        return TenantUser::query()->create([
            'tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id,
            'role' => 'CONSULTANT', 'status' => 'ACTIVE', 'joined_at' => now(),
        ]);
    }
}
