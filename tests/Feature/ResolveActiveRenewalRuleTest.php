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
}
