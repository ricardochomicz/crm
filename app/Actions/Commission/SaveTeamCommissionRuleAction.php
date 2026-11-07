<?php

namespace App\Actions\Commission;

use App\Models\TeamCommissionRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveTeamCommissionRuleAction
{
    /** @param array<string, mixed> $data */
    public function execute(string $tenantId, array $data, ?string $ruleId = null): TeamCommissionRule
    {
        return DB::transaction(function () use ($tenantId, $data, $ruleId): TeamCommissionRule {
            $query = TeamCommissionRule::query()->where('tenant_id', $tenantId);
            $record = $ruleId ? (clone $query)->lockForUpdate()->findOrFail($ruleId) : null;

            if ($data['is_active']) {
                $overlap = (clone $query)
                    ->where('operator_id', $data['operator_id'])
                    ->where('order_type', $data['order_type'])
                    ->where('commercial_role', $data['commercial_role'])
                    ->where('is_active', true)
                    ->where('starts_on', '<=', $data['ends_on'] ?? '9999-12-31')
                    ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $data['starts_on']));

                if ($record) {
                    $overlap->where('id', '!=', $record->id);
                }

                if ($overlap->exists()) {
                    throw ValidationException::withMessages([
                        'starts_on' => 'Já existe uma regra ativa com vigência sobreposta para esta operadora, tipo e papel.',
                    ]);
                }
            }

            if ($record) {
                $record->update($data);
            } else {
                $record = TeamCommissionRule::create(['tenant_id' => $tenantId, ...$data]);
            }

            return $record->fresh('operator:id,name');
        });
    }
}
