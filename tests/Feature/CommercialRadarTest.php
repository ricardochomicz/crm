<?php

namespace Tests\Feature;

use App\Actions\Commercial\BuildCommercialRadarAction;
use App\Models\CalendarEvent;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommercialRadarTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function member(Tenant $tenant): TenantUser
    {
        return TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => User::factory()->create()->id,
            'role' => 'OWNER',
            'status' => 'ACTIVE',
            'joined_at' => now(),
        ]);
    }

    private function activity(Tenant $tenant, TenantUser $member, string $title, string $status = 'pending'): CalendarEvent
    {
        return CalendarEvent::query()->create([
            'tenant_id' => $tenant->id,
            'tenant_user_id' => $member->id,
            'created_by_tenant_user_id' => $member->id,
            'title' => $title,
            'type' => 'task',
            'status' => $status,
            'priority' => 'normal',
            'all_day' => false,
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDay(),
        ]);
    }

    public function test_radar_only_returns_activities_assigned_to_member_in_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->member($tenant);
        $colleague = $this->member($tenant);
        $foreignTenant = Tenant::factory()->create();
        $foreignMember = $this->member($foreignTenant);

        $own = $this->activity($tenant, $actor, 'Minha tarefa vencida');
        $this->activity($tenant, $colleague, 'Tarefa de outro consultor');
        $this->activity($foreignTenant, $foreignMember, 'Tarefa de outra empresa');

        $result = app(BuildCommercialRadarAction::class)->execute($tenant, $actor->user);
        $ids = array_column($result['items'], 'id');

        $this->assertContains($own->id, $ids);
        $this->assertCount(1, $ids);
        $this->assertSame(1, $result['summary']['urgent']);
    }

    public function test_completed_activities_do_not_appear(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->member($tenant);
        $this->activity($tenant, $actor, 'Concluída', 'completed');

        $result = app(BuildCommercialRadarAction::class)->execute($tenant, $actor->user);

        $this->assertSame([], $result['items']);
        $this->assertSame(0, $result['summary']['total']);
    }

    public function test_radar_endpoint_requires_authentication(): void
    {
        $tenant = Tenant::factory()->create();

        $this->getJson("/api/v1/tenants/{$tenant->id}/commercial-radar")
            ->assertUnauthorized();
    }

    public function test_radar_endpoint_rejects_another_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $actor = $this->member($tenant);

        $this->actingAs($actor->user)
            ->getJson("/api/v1/tenants/{$otherTenant->id}/commercial-radar")
            ->assertForbidden();
    }
}
