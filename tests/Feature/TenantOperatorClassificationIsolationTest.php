<?php

namespace Tests\Feature;

use App\Actions\Client\FilterOperatorClassificationsAction;
use App\Actions\Client\SaveOperatorClassificationAction;
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
}
