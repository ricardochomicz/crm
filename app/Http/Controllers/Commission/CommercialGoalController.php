<?php

namespace App\Http\Controllers\Commission;

use App\Actions\Commission\FilterCommercialGoalAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commission\IndexCommercialGoalRequest;
use App\Http\Requests\Commission\SaveCommercialGoalRequest;
use App\Http\Resources\Commission\CommercialGoalResource;
use App\Models\CommercialGoal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommercialGoalController extends Controller
{
    public function index(IndexCommercialGoalRequest $request, string $tenantId, FilterCommercialGoalAction $filter): array
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($request->user()->can('viewCommissions', $tenant), 403);

        return [
            'data' => CommercialGoalResource::collection($filter->execute($tenant->id, $request->validated())),
            'meta' => ['can_manage' => $request->user()->can('manageCommissions', $tenant)],
        ];
    }

    public function store(SaveCommercialGoalRequest $request, string $tenantId): array
    {
        return $this->save($request, null);
    }

    public function update(SaveCommercialGoalRequest $request, string $tenantId, string $goalId): array
    {
        return $this->save($request, $goalId);
    }

    private function save(SaveCommercialGoalRequest $request, ?string $goalId): array
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($request->user()->can('manageCommissions', $tenant), 403);
        $data = $request->validated();
        $minimums = array_map(fn ($tier) => (float) $tier['minimum'], $data['tiers']);
        if (count(array_unique($minimums)) !== count($minimums) || min($minimums) !== 0.0) {
            throw ValidationException::withMessages(['tiers' => 'As faixas devem começar em zero e não podem repetir valores mínimos.']);
        }
        if ($data['metric'] === 'QUANTITY' && collect($data['tiers'])->contains(fn ($tier) => floor((float) $tier['minimum']) !== (float) $tier['minimum'])) {
            throw ValidationException::withMessages(['tiers' => 'Metas por quantidade exigem limites inteiros.']);
        }

        return DB::transaction(function () use ($tenant, $data, $goalId) {
            $query = CommercialGoal::where('tenant_id', $tenant->id);
            $goal = $goalId ? (clone $query)->lockForUpdate()->findOrFail($goalId) : null;
            if ($data['is_active']) {
                $overlap = (clone $query)->where('operator_id', $data['operator_id'])
                    ->where('order_type', $data['order_type'])->where('commercial_role', $data['commercial_role'])
                    ->where('metric', $data['metric'])->where('is_active', true)
                    ->where('starts_on', '<=', $data['ends_on'] ?? '9999-12-31')
                    ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $data['starts_on']));
                if ($goal) {
                    $overlap->where('id', '!=', $goal->id);
                }
                if ($overlap->exists()) {
                    throw ValidationException::withMessages(['starts_on' => 'Já existe uma meta ativa com vigência sobreposta para essa combinação.']);
                }
            }
            $tiers = $data['tiers'];
            unset($data['tiers']);
            if ($goal) {
                $goal->update($data);
            } else {
                $goal = CommercialGoal::create(['tenant_id' => $tenant->id, ...$data]);
            }
            $goal->tiers()->delete();
            foreach ($tiers as $tier) {
                $goal->tiers()->create($tier);
            }

            return ['data' => new CommercialGoalResource($goal->fresh(['operator:id,name', 'tiers']))];
        });
    }
}
