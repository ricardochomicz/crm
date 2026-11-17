<?php

namespace App\Actions\Tenant;

use App\Models\CommissionTable;
use App\Models\OperatorClassification;
use App\Models\Tenant;

class GetTenantOnboardingProgressAction
{
    /** @return array<string, mixed> */
    public function execute(Tenant $tenant): array
    {
        $operatorIds = $tenant->operators()->pluck('operators.id')->all();

        $commissionOperators = CommissionTable::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereIn('operator_id', $operatorIds)
            ->distinct()->pluck('operator_id')->all();

        $classificationOperators = OperatorClassification::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereIn('operator_id', $operatorIds)
            ->distinct()->pluck('operator_id')->all();

        $missingCommissions = array_values(array_diff($operatorIds, $commissionOperators));
        $missingClassifications = array_values(array_diff($operatorIds, $classificationOperators));

        return [
            'completed' => $tenant->onboarding_completed_at !== null,
            'completed_at' => $tenant->onboarding_completed_at?->toIso8601String(),
            'steps' => [
                'company' => ['complete' => true],
                'plan' => ['complete' => $tenant->subscription()->exists()],
                'operators' => ['complete' => count($operatorIds) > 0, 'operator_ids' => $operatorIds],
                'commissions' => ['complete' => count($operatorIds) > 0 && $missingCommissions === [], 'missing_operator_ids' => $missingCommissions],
                'classifications' => ['complete' => count($operatorIds) > 0 && $missingClassifications === [], 'missing_operator_ids' => $missingClassifications],
            ],
        ];
    }
}
