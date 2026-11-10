<?php

namespace App\Actions\Commission;

use App\Models\CommercialGoal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveCommercialGoalAction
{
    /** @param array<string, mixed> $data */
    public function execute(string $tenantId, array $data, ?string $goalId = null): CommercialGoal
    {
        return DB::transaction(function () use ($tenantId, $data, $goalId): CommercialGoal {
            $query = CommercialGoal::query()->where('tenant_id', $tenantId);
            $goal = $goalId ? (clone $query)->lockForUpdate()->findOrFail($goalId) : null;

            if ($data['is_active']) {
                $overlap = (clone $query)->where('operator_id', $data['operator_id'])
                    ->where('commercial_role', $data['commercial_role'])
                    ->where('metric', $data['metric'])
                    ->where('target_scope', $data['target_scope'])
                    ->where('responsible_membership_id', $data['responsible_membership_id'] ?? null)
                    ->where('is_active', true)
                    ->where('starts_on', '<=', $data['ends_on'] ?? '9999-12-31')
                    ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $data['starts_on']));

                if ($goal) {
                    $overlap->where('id', '!=', $goal->id);
                }

                $selected = $data['negotiation_type_ids'] ?? [$data['order_type']];
                $conflict = $overlap->get()->contains(function (CommercialGoal $existing) use ($selected, $data): bool {
                    if ($existing->category && ($data['category'] ?? null) && $existing->category !== $data['category']) {
                        return false;
                    }

                    return count(array_intersect($selected, $existing->negotiation_type_ids ?: [$existing->order_type])) > 0;
                });

                if ($conflict) {
                    throw ValidationException::withMessages([
                        'starts_on' => 'Já existe uma meta ativa com vigência sobreposta para essa combinação.',
                    ]);
                }
            }

            // Keep the existing order_type field for compatibility with older goal reports.
            // The category and selected negotiation types are additional grouping criteria.
            $tiers = $data['tiers'];
            unset($data['tiers']);

            if ($goal) {
                $goal->update($data);
            } else {
                $goal = CommercialGoal::create(['tenant_id' => $tenantId, ...$data]);
            }

            $goal->tiers()->delete();

            foreach ($tiers as $tier) {
                $goal->tiers()->create($tier);
            }

            return $goal->fresh(['operator:id,name', 'tiers']);
        });
    }
}
