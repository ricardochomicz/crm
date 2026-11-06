<?php

namespace App\Http\Controllers\Commission;

use App\Actions\Commission\FilterTeamCommissionRuleAction;
use App\Enums\CommercialRole;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commission\IndexTeamCommissionRuleRequest;
use App\Http\Requests\Commission\SaveTeamCommissionRuleRequest;
use App\Http\Resources\Commission\TeamCommissionRuleResource;
use App\Models\TeamCommissionRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeamCommissionRuleController extends Controller
{
    public function index(IndexTeamCommissionRuleRequest $request, string $tenantId, FilterTeamCommissionRuleAction $filter): array
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($request->user()->can('viewCommissions', $tenant), 403);

        return [
            'data' => TeamCommissionRuleResource::collection($filter->execute($tenant->id, $request->validated())),
            'meta' => ['can_manage' => $request->user()->can('manageCommissions', $tenant)],
        ];
    }

    public function options(Request $request, string $tenantId): array
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($request->user()->can('viewCommissions', $tenant), 403);

        return ['data' => [
            'order_types' => OrderType::options(),
            'roles' => array_map(
                fn (CommercialRole $role): array => ['value' => $role->value, 'label' => $role->label()],
                CommercialRole::cases(),
            ),
        ]];
    }

    public function store(SaveTeamCommissionRuleRequest $request, string $tenantId): array
    {
        return $this->save($request, null);
    }

    public function update(SaveTeamCommissionRuleRequest $request, string $tenantId, string $ruleId): array
    {
        return $this->save($request, $ruleId);
    }

    private function save(SaveTeamCommissionRuleRequest $request, ?string $ruleId): array
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($request->user()->can('manageCommissions', $tenant), 403);

        $data = $request->validated();

        return DB::transaction(function () use ($tenant, $data, $ruleId): array {
            $query = TeamCommissionRule::query()->where('tenant_id', $tenant->id);
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
                $record = TeamCommissionRule::create(['tenant_id' => $tenant->id, ...$data]);
            }

            return ['data' => new TeamCommissionRuleResource($record->fresh('operator:id,name'))];
        });
    }
}
