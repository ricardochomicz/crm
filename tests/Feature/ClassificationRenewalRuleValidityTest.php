<?php

namespace Tests\Feature;

use App\Actions\Client\SaveRenewalRuleAction;
use App\Models\ClassificationRenewalRule;
use App\Models\Operator;
use App\Models\OperatorClassification;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClassificationRenewalRuleValidityTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function classification(): OperatorClassification
    {
        $tenant = Tenant::factory()->create();
        $operator = Operator::factory()->create();

        return OperatorClassification::query()->create([
            'tenant_id' => $tenant->id, 'operator_id' => $operator->id,
            'code' => 'OURO', 'name' => 'Ouro', 'is_active' => true,
        ]);
    }

    private function data(string $from, ?string $until): array
    {
        return [
            'minimum_months' => 18, 'valid_from' => $from,
            'valid_until' => $until, 'is_active' => true, 'notes' => null,
        ];
    }

    public function test_non_overlapping_active_rules_can_be_created(): void
    {
        $classification = $this->classification();
        $action = app(SaveRenewalRuleAction::class);
        $first = $action->execute($classification->tenant_id, $classification->id, $this->data('2026-01-01', '2026-06-30'));
        $second = $action->execute($classification->tenant_id, $classification->id, $this->data('2026-07-01', null));

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, ClassificationRenewalRule::query()->where('classification_id', $classification->id)->count());
    }

    public function test_overlapping_active_rules_are_rejected(): void
    {
        $classification = $this->classification();
        $action = app(SaveRenewalRuleAction::class);
        $action->execute($classification->tenant_id, $classification->id, $this->data('2026-01-01', null));

        $this->expectException(ValidationException::class);
        $action->execute($classification->tenant_id, $classification->id, $this->data('2026-06-01', null));
    }

    public function test_editing_same_rule_does_not_conflict_with_itself(): void
    {
        $classification = $this->classification();
        $action = app(SaveRenewalRuleAction::class);
        $rule = $action->execute($classification->tenant_id, $classification->id, $this->data('2026-01-01', null));
        $edited = $action->execute($classification->tenant_id, $classification->id, [
            ...$this->data('2026-01-01', null), 'minimum_months' => 24,
        ], $rule->id);

        $this->assertSame($rule->id, $edited->id);
        $this->assertSame(24, $edited->fresh()->minimum_months);
    }

    public function test_invalid_date_range_is_rejected_even_outside_http_validation(): void
    {
        $classification = $this->classification();

        $this->expectException(ValidationException::class);
        app(SaveRenewalRuleAction::class)->execute(
            $classification->tenant_id, $classification->id,
            $this->data('2026-12-01', '2026-01-01')
        );
    }
}
