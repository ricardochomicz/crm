<?php

namespace Tests\Feature;

use App\Http\Controllers\Client\ClientTransferController;
use App\Actions\Client\AuthorizeClientTransferReviewAction;
use App\Models\Client;
use App\Models\ClientSeller;
use App\Models\ClientTransferRequest;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ClientTransferApprovalIntegrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_manager_approval_moves_open_opportunities_and_keeps_closed_history(): void
    {
        [$tenant, $client, $source, $destination, $manager, $transfer] = $this->scenario();
        $open = $this->opportunity($tenant, $client, $source, 'open');
        $closed = $this->opportunity($tenant, $client, $source, 'won');

        $response = $this->review($tenant, $manager, $transfer, 'APPROVED');

        self::assertSame(200, $response->status());
        self::assertSame(1, $response->getData(true)['data']['transferred_opportunities_count']);
        self::assertSame('APPROVED', $transfer->fresh()->status);
        self::assertSame($destination->id, $open->fresh()->tenant_user_id);
        self::assertSame($source->id, $closed->fresh()->tenant_user_id);
        $this->assertDatabaseHas('client_sellers', [
            'client_id' => $client->id, 'tenant_user_id' => $destination->id, 'ended_at' => null,
        ]);
        self::assertSame(1, ClientSeller::query()->where('client_id', $client->id)
            ->whereNull('ended_at')->count());
        $this->assertDatabaseHas('opportunity_ownership_transfers', [
            'opportunity_id' => $open->id,
            'from_tenant_user_id' => $source->id,
            'to_tenant_user_id' => $destination->id,
            'actor_user_id' => $manager->user_id,
            'source_type' => 'client_transfer',
            'source_id' => $transfer->id,
        ]);
    }

    public function test_rejection_preserves_assignments_and_opportunities(): void
    {
        [$tenant, $client, $source, , $manager, $transfer] = $this->scenario();
        $open = $this->opportunity($tenant, $client, $source, 'open');

        $response = $this->review($tenant, $manager, $transfer, 'REJECTED');

        self::assertSame(200, $response->status());
        self::assertSame(0, $response->getData(true)['data']['transferred_opportunities_count']);
        self::assertSame('REJECTED', $transfer->fresh()->status);
        self::assertSame($source->id, $open->fresh()->tenant_user_id);
        self::assertSame($source->id, ClientSeller::query()->where('client_id', $client->id)
            ->whereNull('ended_at')->sole()->tenant_user_id);
        $this->assertDatabaseCount('opportunity_ownership_transfers', 0);
    }

    public function test_invalid_opportunity_owner_rolls_back_approval_and_audit(): void
    {
        [$tenant, $client, $source, , $manager, $transfer] = $this->scenario();
        $third = $this->member($tenant, 'CONSULTANT');
        $invalid = $this->opportunity($tenant, $client, $third, 'open');

        try {
            $this->review($tenant, $manager, $transfer, 'APPROVED');
            self::fail('A aprovação deveria falhar.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('client_ids', $exception->errors());
        }

        self::assertSame('PENDING', $transfer->fresh()->status);
        self::assertSame($third->id, $invalid->fresh()->tenant_user_id);
        self::assertSame($source->id, ClientSeller::query()->where('client_id', $client->id)
            ->whereNull('ended_at')->sole()->tenant_user_id);
        $this->assertDatabaseCount('opportunity_ownership_transfers', 0);
    }

    public function test_review_cannot_be_repeated(): void
    {
        [$tenant, , , , $manager, $transfer] = $this->scenario();
        $this->review($tenant, $manager, $transfer, 'REJECTED');

        $this->expectException(HttpException::class);
        $this->review($tenant, $manager, $transfer, 'APPROVED');
    }

    private function review(Tenant $tenant, TenantUser $reviewer, ClientTransferRequest $transfer, string $decision): \Illuminate\Http\JsonResponse
    {
        $request = Request::create('/api/v1/tenants/' . $tenant->id . '/clients/transfers/' . $transfer->id . '/review', 'POST', [
            'decision' => $decision,
            'review_note' => 'Decisão registrada para teste de integração',
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
            'document' => '12345678000195', 'company_name' => 'Cliente de aprovação',
        ]);
        ClientSeller::query()->create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id,
            'tenant_user_id' => $source->id, 'assigned_by' => $manager->user_id,
            'started_at' => now(),
        ]);
        $transfer = ClientTransferRequest::query()->create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id,
            'from_tenant_user_id' => $source->id,
            'to_tenant_user_id' => $destination->id,
            'requested_by' => $destination->id, 'status' => 'PENDING',
            'reason' => 'Solicitação de transferência individual',
        ]);

        return [$tenant, $client, $source, $destination, $manager, $transfer];
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
            'title' => 'Oportunidade individual', 'order_type' => 'NEW', 'status' => $status,
        ]);
    }
}
