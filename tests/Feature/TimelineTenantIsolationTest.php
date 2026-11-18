<?php

namespace Tests\Feature;

use App\Actions\Timeline\TimelineAction;
use App\Models\Client;
use App\Models\Comment;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TimelineTenantIsolationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function owner(Tenant $tenant): TenantUser
    {
        return TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => User::factory()->create()->id,
            'role' => 'OWNER',
            'status' => 'ACTIVE',
            'joined_at' => now(),
        ]);
    }

    private function client(Tenant $tenant, string $document): Client
    {
        return Client::query()->create([
            'tenant_id' => $tenant->id,
            'person_type' => 'PF',
            'document' => $document,
            'company_name' => 'Cliente '.$document,
            'is_active' => true,
        ]);
    }

    public function test_owner_cannot_read_another_tenants_client_timeline(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $owner = $this->owner($tenant);
        $foreignClient = $this->client($otherTenant, '12345678931');

        $this->expectException(ModelNotFoundException::class);

        app(TimelineAction::class)->list($tenant, $owner->user, 'client', $foreignClient->id);
    }

    public function test_owner_cannot_read_another_tenants_comment_by_id(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $owner = $this->owner($tenant);
        $foreignOwner = $this->owner($otherTenant);
        $foreignClient = $this->client($otherTenant, '12345678932');
        $foreignComment = Comment::query()->create([
            'tenant_id' => $otherTenant->id,
            'tenant_user_id' => $foreignOwner->id,
            'commentable_type' => 'client',
            'commentable_id' => $foreignClient->id,
            'content' => 'Informação de outra empresa',
        ]);

        $this->expectException(ModelNotFoundException::class);

        app(TimelineAction::class)->comment($tenant, $owner->user, $foreignComment->id);
    }

    public function test_owner_can_read_own_tenants_client_timeline(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->owner($tenant);
        $client = $this->client($tenant, '12345678933');
        $comment = Comment::query()->create([
            'tenant_id' => $tenant->id,
            'tenant_user_id' => $owner->id,
            'commentable_type' => 'client',
            'commentable_id' => $client->id,
            'content' => 'Registro autorizado',
        ]);

        $result = app(TimelineAction::class)->list($tenant, $owner->user, 'client', $client->id);

        $this->assertSame(1, $result['total']);
        $this->assertSame($comment->id, $result['data'][0]['id']);
    }
}
