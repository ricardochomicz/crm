<?php

namespace Tests\Feature\Commission;

use App\Actions\Commission\CommissionReportAction;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommissionReportTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function member(Tenant $tenant, string $role): TenantUser
    {
        return TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => User::factory()->create()->id,
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }

    private function report(Tenant $tenant, TenantUser $member, array $filters = []): array
    {
        return app(CommissionReportAction::class)->execute($tenant, $member->user, [
            'from' => '2026-11-01',
            'to' => '2026-11-30',
            ...$filters,
        ]);
    }

    public function test_owner_receives_empty_report_without_invented_totals(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->member($tenant, 'OWNER');

        $report = $this->report($tenant, $owner);

        $this->assertSame([], $report['summary']->all());
        $this->assertSame([], $report['participants']);
        $this->assertSame(['from' => '2026-11-01', 'to' => '2026-11-30'], $report['period']);
        $this->assertStringContainsString('não representa pagamento', $report['note']);
    }

    public function test_consultant_cannot_access_company_financial_report(): void
    {
        $tenant = Tenant::factory()->create();
        $consultant = $this->member($tenant, 'CONSULTANT');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->report($tenant, $consultant);
    }

    public function test_company_report_does_not_include_other_tenant_data(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->member($tenant, 'OWNER');
        $other = Tenant::factory()->create();
        $this->member($other, 'OWNER');

        $report = $this->report($tenant, $owner);
        $this->assertCount(0, $report['summary']);
        $this->assertCount(0, $report['participants']);
    }
}
