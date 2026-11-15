<?php

namespace Tests\Feature\Commercial;

use App\Actions\Commercial\ConsultantProductivityAction;
use App\Actions\Tenant\BuildActiveTenantAccessAction;
use App\Enums\TenantPermission;
use Illuminate\Support\Facades\DB;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ConsultantProductivityVisibilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function member(Tenant $tenant, string $role = 'CONSULTANT', ?string $teamId = null): TenantUser
    {
        return TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => User::factory()->create()->id,
            'role' => $role,
            'status' => 'ACTIVE',
            'team_id' => $teamId,
        ]);
    }

    public function test_supervisor_can_see_only_own_team_and_not_other_tenants(): void
    {
        $tenant = Tenant::factory()->create();
        $supervisor = $this->member($tenant);
        $team = Team::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Equipe A',
            'supervisor_membership_id' => $supervisor->id,
        ]);
        $teammate = $this->member($tenant, 'CONSULTANT', $team->id);
        $other = $this->member($tenant);
        $foreign = $this->member(Tenant::factory()->create());

        $result = app(ConsultantProductivityAction::class)->overview($tenant, $supervisor->user, [
            'from' => '2026-11-01',
            'to' => '2026-11-14',
        ]);

        $ids = collect($result['data'])->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$supervisor->id, $teammate->id], $ids);
        $this->assertNotContains($other->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_owner_sees_members_only_from_own_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->member($tenant, 'OWNER');
        $consultant = $this->member($tenant);
        $foreign = $this->member(Tenant::factory()->create());

        $result = app(ConsultantProductivityAction::class)->overview($tenant, $owner->user, [
            'from' => '2026-11-01',
            'to' => '2026-11-14',
        ]);

        $ids = collect($result['data'])->pluck('id')->all();
        $this->assertContains($owner->id, $ids);
        $this->assertContains($consultant->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_consultant_without_team_access_is_forbidden(): void
    {
        $tenant = Tenant::factory()->create();
        $consultant = $this->member($tenant);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(ConsultantProductivityAction::class)->overview($tenant, $consultant->user, [
            'from' => '2026-11-01',
            'to' => '2026-11-14',
        ]);
    }
    private function access(Tenant $tenant, TenantUser $member): array
    {
        $tenant->setRelation('membership', $member);
        return app(BuildActiveTenantAccessAction::class)->execute($member->user, $tenant);
    }

    public function test_supervisor_without_explicit_team_calendar_permission_has_effective_access(): void
    {
        $tenant = Tenant::factory()->create();
        $supervisor = $this->member($tenant);
        $team = Team::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Equipe supervisionada',
            'supervisor_membership_id' => $supervisor->id,
        ]);
        $teammate = $this->member($tenant, 'CONSULTANT', $team->id);
        $unrelated = $this->member($tenant);

        $access = $this->access($tenant, $supervisor);
        $this->assertNotContains(TenantPermission::ViewTeamCalendar->value, $access['permissions']);
        $this->assertTrue($access['can_view_team_productivity']);

        $rows = app(ConsultantProductivityAction::class)->overview($tenant, $supervisor->user, [
            'from' => '2026-11-01', 'to' => '2026-11-14',
        ]);
        $ids = collect($rows['data'])->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$supervisor->id, $teammate->id], $ids);
        $this->assertNotContains($unrelated->id, $ids);
    }

    public function test_unprivileged_consultant_is_denied_in_access_payload_and_action(): void
    {
        $tenant = Tenant::factory()->create();
        $consultant = $this->member($tenant);
        $this->assertFalse($this->access($tenant, $consultant)['can_view_team_productivity']);

        try {
            app(ConsultantProductivityAction::class)->overview($tenant, $consultant->user, [
                'from' => '2026-11-01', 'to' => '2026-11-14',
            ]);
            $this->fail('Expected a forbidden response.');
        } catch (\\Symfony\\Component\\HttpKernel\\Exception\\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_owner_has_effective_access_and_only_own_tenant_data(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->member($tenant, 'OWNER');
        $this->assertTrue($this->access($tenant, $owner)['can_view_team_productivity']);
        $foreign = $this->member(Tenant::factory()->create());

        $rows = app(ConsultantProductivityAction::class)->overview($tenant, $owner->user, [
            'from' => '2026-11-01', 'to' => '2026-11-14',
        ]);
        $this->assertNotContains($foreign->id, collect($rows['data'])->pluck('id')->all());
    }

    public function test_explicit_permission_allows_access_without_supervised_team(): void
    {
        $tenant = Tenant::factory()->create();
        $consultant = $this->member($tenant);
        DB::table('tenant_role_permissions')->updateOrInsert(
            ['tenant_id' => $tenant->id, 'role' => 'CONSULTANT'],
            ['permissions' => json_encode([TenantPermission::ViewTeamCalendar->value]), 'version' => 1]
        );
        $this->assertTrue($this->access($tenant, $consultant)['can_view_team_productivity']);
    }

}
