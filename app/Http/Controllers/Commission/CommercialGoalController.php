<?php

namespace App\Http\Controllers\Commission;

use App\Enums\CommercialRole;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Models\CommercialGoal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommercialGoalController extends Controller
{
    public function index(Request $request, string $tenantId): array
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($request->user()->can('viewCommissions', $tenant), 403);
        return [
            'data' => CommercialGoal::where('tenant_id', $tenant->id)->with(['operator:id,name', 'tiers'])->orderBy('order_type')->get(),
            'meta' => ['can_manage' => $request->user()->can('manageCommissions', $tenant)],
        ];
    }

    public function store(Request $request, string $tenantId): array { return $this->save($request, null); }
    public function update(Request $request, string $tenantId, string $goalId): array { return $this->save($request, $goalId); }

    private function save(Request $request, ?string $goalId): array
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($request->user()->can('manageCommissions', $tenant), 403);
        $data = $request->validate([
            'operator_id' => ['required', 'integer', Rule::exists('tenant_operators', 'operator_id')->where('tenant_id', $tenant->id)],
            'order_type' => ['required', Rule::enum(OrderType::class)],
            'commercial_role' => ['required', Rule::enum(CommercialRole::class)],
            'metric' => ['required', Rule::in(['QUANTITY', 'REVENUE'])],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'is_active' => ['required', 'boolean'],
            'tiers' => ['required', 'array', 'min:1', 'max:30'],
            'tiers.*.minimum' => ['required', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'],
            'tiers.*.percentage' => ['required', 'numeric', 'min:0', 'max:9999.99', 'decimal:0,2'],
        ]);
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
                if ($goal) $overlap->where('id', '!=', $goal->id);
                if ($overlap->exists()) throw ValidationException::withMessages(['starts_on' => 'Já existe uma meta ativa com vigência sobreposta para essa combinação.']);
            }
            $tiers = $data['tiers'];
            unset($data['tiers']);
            if ($goal) $goal->update($data);
            else $goal = CommercialGoal::create(['tenant_id' => $tenant->id, ...$data]);
            $goal->tiers()->delete();
            foreach ($tiers as $tier) $goal->tiers()->create($tier);
            return ['data' => $goal->fresh(['operator:id,name', 'tiers'])];
        });
    }
}
