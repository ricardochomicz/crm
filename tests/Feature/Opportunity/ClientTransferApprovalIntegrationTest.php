<?php

namespace Tests\Feature\Opportunity;

use App\Actions\Client\AuthorizeClientTransferReviewAction;
use App\Http\Controllers\Client\ClientTransferController;
use App\Models\Client;
use App\Models\ClientSeller;
use App\Models\ClientTransferRequest;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClientTransferApprovalIntegrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_manager_approval_transfers_portfolio_and_open_opportunities_with_audit(): void
    {
        [$tenant, $manager, $source, $destination, $client, $transfer] = $this->scenario();
        $open = $this->opportunity($tenant, $client, $source, 'open');
        $closed = $this->opportunity($tenant, $client, $source, 'lost');

        $response = $this->review($tenant, $manager, $transfer, 'APPROVED');
        self::assertSame(200, $response->status());
        self::assertSame('APPROVED', $response->getData(true)['data']['status']);
        self::assertSame(1, $response->getData(true)['data']['transferred_opportunities_count']);
        self::assertSame('APPROVED', $transfer->fresh()->status);
        self::assertSame($destination->id, $open->fresh()->tenant_user_id);
        self::assertSame($source->id, $closed->fresh()->tenant_user_id);
        self::assertSame(1, ClientSeller::query()->where('tenant_id', $tenant->id)
            ->where('client_id', $client->id)->whereNull('ended_at')->count());
        $this->assertDatabaseHas('client_sellers', [
            'tenant_id' => $tenant->id, 'client_id' => $client->id,
            'tenant_user_id' => $destination->id, 'ended_at' => null,
        ]);
        $this->assertDatabaseHas('opportunity_ownership_transfers', [
            'tenant_id' => $tenant->id, 'client_id' => $client->id,
            'opportunity_id' => $open->id, 'source_type' => 'client_transfer',
            'source_id' => $transfer->id, 'from_tenant_user_id' => $source->id,
            'to_tenant_user_id' => $destination->id, 'actor_user_id' => $manager->user_id,
        ]);
    }

    public function test_rejection_preserves_assignments_and_opportunities(): void
    {
        [$tenant, $manager, $source, , $client, $transfer] = $this->scenario();
        $open = $this->opportunity($tenant, $client, $source, 'open');

        $response = $this->review($tenant, $manager, $transfer, 'REJECTED');

        self::assertSame(200, $response->status());
        self::assertSame(0, $response->getData(true)['data']['transferred_opportunities_count']);
        self::assertSame('REJECTED', $transfer->fresh()->status);
        self::assertSame($source->id, $open->fresh()->tenant_user_id);
        $this->assertDatabaseHas('client_sellers', [
            'client_id' => $client->id, 'tenant_user_id' => $source->id, 'ended_at' => null,
        ]);
        $this->assertDatabaseCount('opportunity_ownership_transfers', 0);
    }

    public function test_unprivileged_reviewer_cannot_approve_transfer(): void
    {
        [$tenant, , $source, , $client, $transfer] = $this->scenario();
        $stranger = $this->member($tenant, 'CONSULTANT');
        $open = $this->opportunity($tenant, $client, $source, 'open');

        try {
            $this->review($tenant, $stranger, $transfer, 'APPROVED');
            self::fail('O consultor não supervisor não deve aprovar a transferência.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
        }

        self::assertSame('PENDING', $transfer->fresh()->status);
        self::assertSame($source->id, $open->fresh()->tenant_user_id);
        $this->assertDatabaseCount('opportunity_ownership_transfers', 0);
    }

    public function test_invalid_opportunity_owner_rolls_back_individual_approval(): void
    {
        [$tenant, $manager, $source, , $client, $transfer] = $this->scenario();
        $third = $this->member($tenant, 'CONSULTANT');
        $open = $this->opportunity($tenant, $client, $third, 'open');

        try {
            $this->review($tenant, $manager, $transfer, 'APPROVED');
            self::fail('A aprovação deveria ser bloqueada.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            self::assertArrayHasKey('client_ids', $exception->errors());
        }

        self::assertSame('PENDING', $transfer->fresh()->status);
        self::assertSame($third->id, $open->fresh()->tenant_user_id);
        $this->assertDatabaseHas('client_sellers', [
            'client_id' => $client->id, 'tenant_user_id' => $source->id, 'ended_at' => null,
        ]);
        $this->assertDatabaseCount('opportunity_ownership_transfers', 0);
    }

    private function review(Tenant $tenant, TenantUser $reviewer, ClientTransferRequest $transfer, string $decision): \Illuminate\Http\JsonResponse
    {
        $request = Request::create('/review', 'PATCH', [
            'decision' => $decision,
            'review_note' => 'Decisão registrada pelo gestor responsável.',
        ]);
        $request->attributes->set('active_tenant', $tenant);
        $request->setUserResolver(fn () => $reviewer->user);

        return app(ClientTransferController::class)->review(
            $request, $tenant->id, $transfer->id, app(AuthorizeClientTransferReviewAction::class)
        );
    }

    private function scenario(): array
    {
        $tenant = Tenant::factory()->create();
        $manager = $this->member($tenant, 'MANAGER');
        $source = $this->member($tenant, 'CONSULTANT');
        $destination = $this->member($tenant, 'CONSULTANT');
        $client = Client::query()->create([
            'tenant_id' => $tenant->id, 'person_type' => 'PJ',
            'document' => '12345678000195', 'company_name' => 'Cliente em transferência',
        ]);
        ClientSeller::query()->create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id,
            'tenant_user_id' => $source->id, 'assigned_by' => $manager->user_id,
            'started_at' => now(),
        ]);
        $transfer = ClientTransferRequest::query()->create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id,
            'from_tenant_user_id' => $source->id, 'to_tenant_user_id' => $destination->id,
            'requested_by' => $destination->id, 'status' => 'PENDING',
            'reason' => 'Solicitação de transferência de carteira',
        ]);

        return [$tenant, $manager, $source, $destination, $client, $transfer];
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
            'title' => 'Oportunidade de transferência', 'order_type' => 'NEW',
            'status' => $status,
        ]);
    }
}
