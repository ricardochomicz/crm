<?php

namespace Tests\Feature\Opportunity;

use App\Actions\Opportunity\OpportunityAction;
use App\Models\Client;
use App\Models\Opportunity;
use App\Models\OpportunityFunnelStage;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OpportunityMutationAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_consultant_cannot_move_another_consultants_opportunity(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->member($tenant, 'CONSULTANT');
        $colleague = $this->member($tenant, 'CONSULTANT');
        $stage = OpportunityFunnelStage::query()->create([
            'tenant_id' => $tenant->id, 'name' => 'Prospecção', 'position' => 0, 'is_active' => true,
        ]);
        $client = Client::query()->create([
            'tenant_id' => $tenant->id, 'person_type' => 'PJ', 'company_name' => 'Cliente de teste',
        ]);
        $opportunity = Opportunity::query()->create([
            'tenant_id' => $tenant->id, 'tenant_user_id' => $colleague->id,
            'client_id' => $client->id, 'code' => 'SCOPE-001',
            'order_type' => 'Novo', 'funnel_stage_id' => $stage->id,
            'title' => 'Oportunidade restrita', 'status' => 'open',
        ]);

        try {
            app(OpportunityAction::class)->moveStage($tenant, $owner->user, $opportunity->id, $stage->id);
            self::fail('A movimentação de uma oportunidade de outro consultor deveria ser negada.');
        } catch (HttpException $exception) {
            self::assertSame(404, $exception->getStatusCode());
        }

        self::assertSame($stage->id, $opportunity->fresh()->funnel_stage_id);
    }

    private function member(Tenant $tenant, string $role): TenantUser
    {
        return TenantUser::query()->create([
            'tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id,
            'role' => $role, 'status' => 'ACTIVE', 'joined_at' => now(),
        ]);
    }
}
