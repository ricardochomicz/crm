<?php

namespace Tests\Feature;

use App\Actions\Client\FilterOperatorClassificationsAction;
use App\Actions\Client\SaveOperatorClassificationAction;
use App\Actions\Client\SaveRenewalRuleAction;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use App\Models\Operator;
use App\Models\OperatorClassification;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TenantOperatorClassificationIsolationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_tenant_list_excludes_global_and_other_tenant_classifications(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $operator = Operator::factory()->create();

        OperatorClassification::query()->create([
            'operator_id' => $operator->id, 'tenant_id' => null, 'code' => 'GLOBAL',
            'name' => 'Global', 'is_active' => true,
        ]);
        OperatorClassification::query()->create([
            'operator_id' => $operator->id, 'tenant_id' => $otherTenant->id, 'code' => 'OTHER',
            'name' => 'Outra empresa', 'is_active' => true,
        ]);
        $own = OperatorClassification::query()->create([
            'operator_id' => $operator->id, 'tenant_id' => $tenant->id, 'code' => 'OWN',
            'name' => 'Própria', 'is_active' => true,
        ]);

        $ids = app(FilterOperatorClassificationsAction::class)->execute($tenant->id)->pluck('id')->all();

        $this->assertSame([$own->id], $ids);
    }

    public function test_tenant_can_reuse_global_classification_code_without_changing_global_record(): void
    {
        $tenant = Tenant::factory()->create();
        $operator = Operator::factory()->create();
        $global = OperatorClassification::query()->create([
            'operator_id' => $operator->id, 'tenant_id' => null, 'code' => 'OURO',
            'name' => 'Ouro global', 'is_active' => true,
        ]);

        $own = app(SaveOperatorClassificationAction::class)->execute($tenant->id, [
            'operator_id' => $operator->id, 'code' => 'OURO',
            'name' => 'Ouro personalizado', 'is_active' => true,
        ]);

        $this->assertNotSame($global->id, $own->id);
        $this->assertSame($tenant->id, $own->tenant_id);
        $this->assertSame('Ouro global', $global->fresh()->name);
    }
    public function test_tenant_cannot_edit_another_company_classification(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $operator = Operator::factory()->create();
        $foreign = OperatorClassification::query()->create([
            'operator_id' => $operator->id, 'tenant_id' => $otherTenant->id,
            'code' => 'PLATINA', 'name' => 'Platina', 'is_active' => true,
        ]);

        $this->expectException(ModelNotFoundException::class);
        app(SaveOperatorClassificationAction::class)->execute($tenant->id, [
            'operator_id' => $operator->id, 'code' => 'PLATINA',
            'name' => 'Alterada', 'is_active' => true,
        ], $foreign->id);
    }

    public function test_tenant_cannot_add_rule_to_global_classification(): void
    {
        $tenant = Tenant::factory()->create();
        $operator = Operator::factory()->create();
        $global = OperatorClassification::query()->create([
            'operator_id' => $operator->id, 'tenant_id' => null,
            'code' => 'GLOBAL', 'name' => 'Global', 'is_active' => true,
        ]);

        $this->expectException(ModelNotFoundException::class);
        app(SaveRenewalRuleAction::class)->execute($tenant->id, $global->id, [
            'minimum_months' => 18,
            'valid_from' => '2026-01-01',
            'valid_until' => null,
            'is_active' => true,
            'notes' => null,
        ]);
    }

    public function test_duplicate_code_is_rejected_only_within_same_company_and_operator(): void
    {
        $tenant = Tenant::factory()->create();
        $operator = Operator::factory()->create();
        OperatorClassification::query()->create([
            'operator_id' => $operator->id, 'tenant_id' => $tenant->id,
            'code' => 'OURO', 'name' => 'Ouro', 'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);
        app(SaveOperatorClassificationAction::class)->execute($tenant->id, [
            'operator_id' => $operator->id, 'code' => 'OURO',
            'name' => 'Duplicada', 'is_active' => true,
        ]);
    }

}
