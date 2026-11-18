<?php

namespace Tests\Feature;

use App\Actions\Client\ResolveActiveRenewalRuleAction;
use App\Models\ClassificationRenewalRule;
use App\Models\Operator;
use App\Models\OperatorClassification;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ResolveActiveRenewalRuleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_resolves_only_current_tenant_rule_without_global_fallback(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $operator = Operator::factory()->create();
        $classification = OperatorClassification::query()->create([
            'tenant_id' => $tenant->id, 'operator_id' => $operator->id,
            'code' => 'OURO', 'name' => 'Ouro', 'is_active' => true,
        ]);
        $base = [
            'classification_id' => $classification->id,
            'valid_from' => '2026-01-01',
            'valid_until' => null,
            'is_active' => true,
        ];
        ClassificationRenewalRule::query()->create([...$base, 'tenant_id' => null, 'minimum_months' => 12]);
        ClassificationRenewalRule::query()->create([...$base, 'tenant_id' => $other->id, 'minimum_months' => 15]);
        $current = ClassificationRenewalRule::query()->create([
            ...$base, 'tenant_id' => $tenant->id, 'minimum_months' => 18,
            'valid_from' => '2026-07-01',
        ]);
        ClassificationRenewalRule::query()->create([
            ...$base, 'tenant_id' => $tenant->id, 'minimum_months' => 24,
            'valid_from' => '2027-01-01',
        ]);

        $resolver = app(ResolveActiveRenewalRuleAction::class);
        $this->assertNull($resolver->execute($tenant->id, $classification->id, CarbonImmutable::parse('2026-06-01')));
        $this->assertSame($current->id, $resolver->execute($tenant->id, $classification->id, CarbonImmutable::parse('2026-11-18'))?->id);
        $this->assertNull($resolver->execute($tenant->id, null, CarbonImmutable::parse('2026-11-18')));
    }
    public function test_rejects_legacy_rule_when_classification_belongs_to_another_scope(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $operator = Operator::factory()->create();

        $global = OperatorClassification::query()->create([
            'tenant_id' => null, 'operator_id' => $operator->id,
            'code' => 'GLOBAL', 'name' => 'Global', 'is_active' => true,
        ]);
        $foreign = OperatorClassification::query()->create([
            'tenant_id' => $other->id, 'operator_id' => $operator->id,
            'code' => 'FOREIGN', 'name' => 'Outra empresa', 'is_active' => true,
        ]);
        $owned = OperatorClassification::query()->create([
            'tenant_id' => $tenant->id, 'operator_id' => $operator->id,
            'code' => 'OWN', 'name' => 'Própria', 'is_active' => true,
        ]);

        foreach ([$global, $foreign, $owned] as $classification) {
            ClassificationRenewalRule::query()->create([
                'tenant_id' => $tenant->id,
                'classification_id' => $classification->id,
                'minimum_months' => 18,
                'valid_from' => '2026-01-01',
                'valid_until' => null,
                'is_active' => true,
            ]);
        }

        $resolver = app(ResolveActiveRenewalRuleAction::class);
        $today = CarbonImmutable::parse('2026-11-18');

        $this->assertNull($resolver->execute($tenant->id, $global->id, $today));
        $this->assertNull($resolver->execute($tenant->id, $foreign->id, $today));
        $this->assertNotNull($resolver->execute($tenant->id, $owned->id, $today));
        $this->assertSame(
            [$owned->id],
            $resolver->query($tenant->id, $today)->pluck('classification_id')->all()
        );
    }
    public function test_rule_is_effective_only_within_its_dates_and_while_active(): void
    {
        $tenant = Tenant::factory()->create();
        $operator = Operator::factory()->create();
        $classification = OperatorClassification::query()->create([
            'tenant_id' => $tenant->id, 'operator_id' => $operator->id,
            'code' => 'OURO', 'name' => 'Ouro', 'is_active' => true,
        ]);
        $rule = ClassificationRenewalRule::query()->create([
            'tenant_id' => $tenant->id,
            'classification_id' => $classification->id,
            'minimum_months' => 18,
            'valid_from' => '2026-03-01',
            'valid_until' => '2026-09-30',
            'is_active' => true,
        ]);

        $resolver = app(ResolveActiveRenewalRuleAction::class);
        $this->assertNull($resolver->execute($tenant->id, $classification->id, CarbonImmutable::parse('2026-02-28')));
        $this->assertSame($rule->id, $resolver->execute($tenant->id, $classification->id, CarbonImmutable::parse('2026-03-01'))?->id);
        $this->assertSame($rule->id, $resolver->execute($tenant->id, $classification->id, CarbonImmutable::parse('2026-09-30'))?->id);
        $this->assertNull($resolver->execute($tenant->id, $classification->id, CarbonImmutable::parse('2026-10-01')));

        $rule->update(['is_active' => false]);
        $this->assertNull($resolver->execute($tenant->id, $classification->id, CarbonImmutable::parse('2026-06-01')));
    }
}
