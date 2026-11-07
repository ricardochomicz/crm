<?php

namespace Tests\Feature;

use App\Actions\Client\DeleteClientAction;
use App\Actions\Client\FilterClientsAction;
use App\Actions\Client\SaveClientContactAction;
use App\Actions\Client\ScopeVisibleClientsAction;
use App\Actions\Client\ShowClientAction;
use App\Models\Client;
use App\Models\ClientSeller;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ClientVisibilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function member(Tenant $tenant, string $role = 'CONSULTANT', ?string $teamId = null): TenantUser
    {
        return TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => User::factory()->create()->id,
            'role' => $role,
            'status' => 'ACTIVE',
            'joined_at' => now(),
            'team_id' => $teamId,
        ]);
    }

    private function client(Tenant $tenant, TenantUser $seller, string $document): Client
    {
        $client = Client::query()->create([
            'tenant_id' => $tenant->id,
            'person_type' => 'PF',
            'document' => $document,
            'company_name' => 'Cliente '.$document,
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

    public function test_consultant_sees_only_current_portfolio(): void
    {
        $tenant = Tenant::factory()->create();
        $first = $this->member($tenant);
        $second = $this->member($tenant);
        $own = $this->client($tenant, $first, '12345678901');
        $other = $this->client($tenant, $second, '12345678902');

        $scope = app(ScopeVisibleClientsAction::class);
        $this->assertSame([$own->id], $scope->execute(Client::query(), $tenant->id, $first->user_id)->pluck('id')->all());
        $this->assertSame(1, app(FilterClientsAction::class)->paginate($tenant->id, [], $first->user_id)->total());
        $this->expectException(ModelNotFoundException::class);
        app(ShowClientAction::class)->execute($tenant->id, $other->id, $first->user_id);
    }

    public function test_supervisor_sees_only_supervised_team_and_own_clients(): void
    {
        $tenant = Tenant::factory()->create();
        $supervisor = $this->member($tenant);
        $teamA = Team::factory()->supervisedBy($supervisor)->create();
        $teamB = Team::factory()->create(['tenant_id' => $tenant->id]);
        $sellerA = $this->member($tenant, 'CONSULTANT', $teamA->id);
        $sellerB = $this->member($tenant, 'CONSULTANT', $teamB->id);
        $own = $this->client($tenant, $supervisor, '12345678903');
        $visible = $this->client($tenant, $sellerA, '12345678904');
        $hidden = $this->client($tenant, $sellerB, '12345678905');

        $ids = app(ScopeVisibleClientsAction::class)
            ->execute(Client::query(), $tenant->id, $supervisor->user_id)->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$own->id, $visible->id], $ids);
        $this->assertNotContains($hidden->id, $ids);
    }

    public function test_manager_and_owner_see_entire_tenant_but_not_other_tenants(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $seller = $this->member($tenant);
        $client = $this->client($tenant, $seller, '12345678906');
        $otherClient = $this->client($otherTenant, $this->member($otherTenant), '12345678907');

        foreach (['MANAGER', 'OWNER'] as $role) {
            $member = $this->member($tenant, $role);
            $ids = app(ScopeVisibleClientsAction::class)
                ->execute(Client::query(), $tenant->id, $member->user_id)->pluck('id')->all();
            $this->assertContains($client->id, $ids);
            $this->assertNotContains($otherClient->id, $ids);
        }
    }

    public function test_out_of_scope_client_cannot_be_deleted_or_have_contacts_changed(): void
    {
        $tenant = Tenant::factory()->create();
        $consultant = $this->member($tenant);
        $client = $this->client($tenant, $this->member($tenant), '12345678908');

        try {
            app(DeleteClientAction::class)->execute($tenant->id, $client->id, $consultant->user_id);
            $this->fail('Exclusão deveria ser bloqueada.');
        } catch (ModelNotFoundException) {
            $this->assertDatabaseHas('clients', ['id' => $client->id, 'deleted_at' => null]);
        }

        $this->expectException(ModelNotFoundException::class);
        app(SaveClientContactAction::class)->execute($tenant->id, $client->id, [
            'name' => 'Contato indevido', 'is_primary' => false, 'is_active' => true,
        ], $consultant->user_id);
    }

    public function test_suspended_member_has_no_visible_clients(): void
    {
        $tenant = Tenant::factory()->create();
        $seller = $this->member($tenant);
        $this->client($tenant, $seller, '12345678909');
        $seller->update(['status' => 'SUSPENDED']);

        $this->assertSame(0, app(ScopeVisibleClientsAction::class)
            ->execute(Client::query(), $tenant->id, $seller->user_id)->count());
    }
}
