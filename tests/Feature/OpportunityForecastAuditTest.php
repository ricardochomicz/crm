<?php

namespace Tests\Feature;

use App\Actions\Opportunity\RecordOpportunityForecastChangeAction;
use App\Models\Opportunity;
use App\Models\OpportunityForecastChange;
use App\Models\OpportunityFunnelStage;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OpportunityForecastAuditTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array{Opportunity, TenantUser, OpportunityFunnelStage} */
    private function scenario(): array
    {
        $tenant = Tenant::factory()->create();
        $actor = TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => User::factory()->create()->id,
            'role' => 'OWNER',
            'status' => 'ACTIVE',
            'joined_at' => now(),
        ]);
        $stage = OpportunityFunnelStage::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Prospecção',
            'position' => 0,
            'is_active' => true,
        ]);
        // The action only needs an existing opportunity, not its commercial items.
        // Disable FK checks is intentionally avoided: use a valid minimal model.
        $opportunity = Opportunity::query()->create([
            'tenant_id' => $tenant->id,
            'tenant_user_id' => $actor->id,
            'funnel_stage_id' => $stage->id,
            'title' => 'Oportunidade de auditoria',
            'status' => 'open',
        ]);

        return [$opportunity, $actor, $stage];
    }

    private function record(Opportunity $opportunity, TenantUser $actor, OpportunityFunnelStage $stage, ?string $before, string $after, ?string $reason = null): void
    {
        app(RecordOpportunityForecastChangeAction::class)->execute(
            $opportunity, $actor, $before, $after,
            $stage->id, $stage->id, $reason, 'Previsão alterada no formulário.'
        );
    }

    public function test_extension_requires_reason_and_writes_nothing_on_validation_failure(): void
    {
        [$opportunity, $actor, $stage] = $this->scenario();

        try {
            $this->record($opportunity, $actor, $stage, '2026-11-10', '2026-11-20', 'Curto');
            $this->fail('A prorrogação sem justificativa válida deveria ser recusada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('forecast_reason', $exception->errors());
        }

        $this->assertDatabaseCount('opportunity_forecast_changes', 0);
        $this->assertDatabaseCount('comments', 0);
    }

    public function test_extension_creates_a_structured_change_and_a_timeline_comment(): void
    {
        [$opportunity, $actor, $stage] = $this->scenario();

        DB::transaction(fn () => $this->record(
            $opportunity, $actor, $stage, '2026-11-10', '2026-11-20', 'Cliente solicitou mais prazo.'
        ));

        $this->assertDatabaseHas('opportunity_forecast_changes', [
            'tenant_id' => $opportunity->tenant_id,
            'opportunity_id' => $opportunity->id,
            'tenant_user_id' => $actor->id,
            'previous_date' => '2026-11-10',
            'new_date' => '2026-11-20',
            'reason' => 'Cliente solicitou mais prazo.',
        ]);
        $this->assertDatabaseHas('comments', [
            'tenant_id' => $opportunity->tenant_id,
            'commentable_type' => 'opportunity',
            'commentable_id' => $opportunity->id,
        ]);
        $this->assertDatabaseCount('opportunity_forecast_changes', 1);
        $this->assertDatabaseCount('comments', 1);
    }

    public function test_advance_is_recorded_without_requiring_a_reason(): void
    {
        [$opportunity, $actor, $stage] = $this->scenario();

        DB::transaction(fn () => $this->record($opportunity, $actor, $stage, '2026-11-20', '2026-11-10'));

        $change = OpportunityForecastChange::query()->sole();
        $this->assertSame('2026-11-20', $change->previous_date->format('Y-m-d'));
        $this->assertSame('2026-11-10', $change->new_date->format('Y-m-d'));
        $this->assertNull($change->reason);
    }

    public function test_unchanged_forecast_does_not_create_audit_records(): void
    {
        [$opportunity, $actor, $stage] = $this->scenario();

        DB::transaction(fn () => $this->record($opportunity, $actor, $stage, '2026-11-20', '2026-11-20'));

        $this->assertDatabaseCount('opportunity_forecast_changes', 0);
        $this->assertDatabaseCount('comments', 0);
    }

    public function test_transaction_rollback_removes_both_audit_and_comment(): void
    {
        [$opportunity, $actor, $stage] = $this->scenario();

        try {
            DB::transaction(function () use ($opportunity, $actor, $stage): void {
                $this->record($opportunity, $actor, $stage, '2026-11-10', '2026-11-20', 'Cliente solicitou mais prazo.');
                throw new \RuntimeException('Falha após gravação');
            });
            $this->fail('A transação deveria falhar.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Falha após gravação', $exception->getMessage());
        }

        $this->assertDatabaseCount('opportunity_forecast_changes', 0);
        $this->assertDatabaseCount('comments', 0);
    }
}
