<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\OperatorClassification;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LegacyOperatorClassificationMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_global_classification_with_tenant_rule_is_cloned_without_changing_global_record(): void
    {
        $tenant = Tenant::factory()->create();
        $operator = Operator::factory()->create();
        $global = OperatorClassification::query()->create([
            'operator_id' => $operator->id,
            'tenant_id' => null,
            'code' => 'OURO',
            'name' => 'Ouro legado',
            'is_active' => true,
        ]);
        $ruleId = (string) Str::uuid();
        DB::table('classification_renewal_rules')->insert([
            'id' => $ruleId,
            'tenant_id' => $tenant->id,
            'classification_id' => $global->id,
            'minimum_months' => 18,
            'valid_from' => '2026-01-01',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_11_18_000001_migrate_used_global_classifications_to_tenants.php');
        $migration->up();

        $owned = OperatorClassification::query()
            ->where('tenant_id', $tenant->id)->where('operator_id', $operator->id)->firstOrFail();

        $this->assertNotSame($global->id, $owned->id);
        $this->assertSame('OURO', $owned->code);
        $this->assertNull($global->fresh()->tenant_id);
        $this->assertDatabaseHas('classification_renewal_rules', [
            'id' => $ruleId, 'tenant_id' => $tenant->id, 'classification_id' => $owned->id,
        ]);
    }

    public function test_existing_company_code_is_not_overwritten_when_migrating_legacy_rule(): void
    {
        $tenant = Tenant::factory()->create();
        $operator = Operator::factory()->create();
        $global = OperatorClassification::query()->create([
            'operator_id' => $operator->id, 'tenant_id' => null,
            'code' => 'OURO', 'name' => 'Legado', 'is_active' => true,
        ]);
        $existing = OperatorClassification::query()->create([
            'operator_id' => $operator->id, 'tenant_id' => $tenant->id,
            'code' => 'OURO', 'name' => 'Personalizada', 'is_active' => true,
        ]);
        $ruleId = (string) Str::uuid();
        DB::table('classification_renewal_rules')->insert([
            'id' => $ruleId, 'tenant_id' => $tenant->id,
            'classification_id' => $global->id, 'minimum_months' => 24,
            'valid_from' => '2026-01-01', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_11_18_000001_migrate_used_global_classifications_to_tenants.php');
        $migration->up();

        $this->assertSame('Personalizada', $existing->fresh()->name);
        $this->assertDatabaseHas('operator_classifications', [
            'tenant_id' => $tenant->id,
            'operator_id' => $operator->id,
            'code' => 'OURO-legacy-' . $global->id,
            'name' => 'Legado',
        ]);
    }
}
