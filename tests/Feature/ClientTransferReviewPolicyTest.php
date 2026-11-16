<?php

namespace Tests\Feature;

use App\Actions\Client\AuthorizeClientTransferReviewAction;
use App\Models\Client;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClientTransferReviewPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_supervisor_cannot_approve_recently_active_client(): void
    {
        $tenant = Tenant::factory()->create();
        $reviewer = $this->member($tenant, 'CONSULTANT');
        $client = $this->client($tenant);

        $this->expectException(ValidationException::class);
        app(AuthorizeClientTransferReviewAction::class)->execute($client, $reviewer);
    }

    public function test_manager_can_approve_exception_with_recent_activity(): void
    {
        $tenant = Tenant::factory()->create();
        $manager = $this->member($tenant, 'MANAGER');
        $result = app(AuthorizeClientTransferReviewAction::class)
            ->execute($this->client($tenant), $manager);

        self::assertFalse($result['inactive_for_90_days']);
    }

    public function test_supervisor_can_approve_inactive_client_without_pending_work(): void
    {
        $tenant = Tenant::factory()->create();
        $reviewer = $this->member($tenant, 'CONSULTANT');
        $client = $this->client($tenant);
        $client->forceFill(['created_at' => now()->subDays(100)])->save();

        $result = app(AuthorizeClientTransferReviewAction::class)->execute($client->fresh(), $reviewer);

        self::assertTrue($result['eligible']);
        self::assertFalse($result['requires_special_review']);
    }

    private function client(Tenant $tenant): Client
    {
        return Client::query()->create([
            'tenant_id' => $tenant->id,
            'person_type' => 'PJ',
            'document' => '12345678000195',
            'company_name' => 'Cliente de transferência',
        ]);
    }

    private function member(Tenant $tenant, string $role): TenantUser
    {
        return TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => User::factory()->create()->id,
            'role' => $role,
            'status' => 'ACTIVE',
            'joined_at' => now(),
        ]);
    }
}
