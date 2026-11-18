<?php

namespace Tests\Feature;

use App\Actions\Timeline\TimelineAction;
use App\Models\Attachment;
use App\Models\Client;
use App\Models\Comment;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

    public function test_owner_cannot_download_attachment_from_another_tenant(): void
    {
        Storage::fake('local');

        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $owner = $this->owner($tenant);
        $foreignOwner = $this->owner($otherTenant);
        $foreignClient = $this->client($otherTenant, '12345678934');
        $comment = Comment::query()->create([
            'tenant_id' => $otherTenant->id,
            'tenant_user_id' => $foreignOwner->id,
            'commentable_type' => 'client',
            'commentable_id' => $foreignClient->id,
            'content' => 'Arquivo privado',
        ]);
        $path = 'tenants/'.$otherTenant->id.'/comments/'.$comment->id.'/private.txt';
        Storage::disk('local')->put($path, 'Conteúdo confidencial');
        $attachment = Attachment::query()->create([
            'tenant_id' => $otherTenant->id,
            'tenant_user_id' => $foreignOwner->id,
            'comment_id' => $comment->id,
            'original_name' => 'private.txt',
            'storage_path' => $path,
            'mime_type' => 'text/plain',
            'size_bytes' => 21,
        ]);

        $this->expectException(ModelNotFoundException::class);

        app(TimelineAction::class)->download($tenant, $owner->user, $attachment->id);
    }

    public function test_owner_can_download_attachment_from_own_client_timeline(): void
    {
        Storage::fake('local');

        $tenant = Tenant::factory()->create();
        $owner = $this->owner($tenant);
        $client = $this->client($tenant, '12345678935');
        $comment = Comment::query()->create([
            'tenant_id' => $tenant->id,
            'tenant_user_id' => $owner->id,
            'commentable_type' => 'client',
            'commentable_id' => $client->id,
            'content' => 'Documento autorizado',
        ]);
        $path = 'tenants/'.$tenant->id.'/comments/'.$comment->id.'/document.txt';
        Storage::disk('local')->put($path, 'Documento de teste');
        $attachment = Attachment::query()->create([
            'tenant_id' => $tenant->id,
            'tenant_user_id' => $owner->id,
            'comment_id' => $comment->id,
            'original_name' => 'document.txt',
            'storage_path' => $path,
            'mime_type' => 'text/plain',
            'size_bytes' => 18,
        ]);

        $response = app(TimelineAction::class)->download($tenant, $owner->user, $attachment->id);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('document.txt', $response->headers->get('content-disposition'));
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
