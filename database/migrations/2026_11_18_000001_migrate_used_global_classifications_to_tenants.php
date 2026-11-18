<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Preserve historical assignments when retiring global classifications from tenant lists.
     * Global records remain intact for the platform and for safe rollback.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $references = [
                'clients',
                'client_operator_history',
                'client_operator_classifications',
                'client_contracts',
                'commercial_orders',
            ];

            $globals = DB::table('operator_classifications')->whereNull('tenant_id')->orderBy('id')->get();

            foreach ($globals as $global) {
                $tenantIds = collect();
                foreach ($references as $table) {
                    $tenantIds = $tenantIds->merge(
                        DB::table($table)->where('classification_id', $global->id)
                            ->distinct()->pluck('tenant_id')
                    );
                }
                $tenantIds = $tenantIds->merge(
                    DB::table('classification_renewal_rules')
                        ->where('classification_id', $global->id)
                        ->whereNotNull('tenant_id')->distinct()->pluck('tenant_id')
                )->unique()->filter();

                foreach ($tenantIds as $tenantId) {
                    // Never repoint historical records to a different classification just
                    // because an owner already created a classification with the same code.
                    $code = $global->code;
                    if (DB::table('operator_classifications')
                        ->where('tenant_id', $tenantId)
                        ->where('operator_id', $global->operator_id)
                        ->where('code', $code)->exists()) {
                        $code = substr($global->code, 0, 18) . '-legacy-' . $global->id;
                    }

                    $classificationId = DB::table('operator_classifications')->insertGetId([
                        'tenant_id' => $tenantId,
                        'operator_id' => $global->operator_id,
                        'code' => $code,
                        'name' => $global->name,
                        'description' => $global->description,
                        'is_active' => $global->is_active,
                        'sort_order' => $global->sort_order,
                        'created_at' => $global->created_at,
                        'updated_at' => now(),
                    ]);

                    foreach ($references as $table) {
                        DB::table($table)->where('tenant_id', $tenantId)
                            ->where('classification_id', $global->id)
                            ->update(['classification_id' => $classificationId]);
                    }

                    // Existing tenant-specific rules belong to the cloned classification.
                    DB::table('classification_renewal_rules')
                        ->where('tenant_id', $tenantId)
                        ->where('classification_id', $global->id)
                        ->update(['classification_id' => $classificationId]);

                    // Copy global defaults only if this company has no rule of its own.
                    $hasTenantRules = DB::table('classification_renewal_rules')
                        ->where('tenant_id', $tenantId)
                        ->where('classification_id', $classificationId)->exists();
                    if (! $hasTenantRules) {
                        $rules = DB::table('classification_renewal_rules')
                            ->whereNull('tenant_id')
                            ->where('classification_id', $global->id)->get();
                        foreach ($rules as $rule) {
                            DB::table('classification_renewal_rules')->insert([
                                'id' => (string) \Illuminate\Support\Str::uuid(),
                                'tenant_id' => $tenantId,
                                'classification_id' => $classificationId,
                                'minimum_months' => $rule->minimum_months,
                                'valid_from' => $rule->valid_from,
                                'valid_until' => $rule->valid_until,
                                'is_active' => $rule->is_active,
                                'notes' => $rule->notes,
                                'created_at' => $rule->created_at,
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }
        });
    }

    public function down(): void
    {
        // Deliberately irreversible: restoring global assignments would discard
        // tenant edits made after migration. Preserve all records.
    }
};
