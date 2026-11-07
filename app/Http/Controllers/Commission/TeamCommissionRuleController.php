<?php

namespace App\Http\Controllers\Commission;

use App\Actions\Commission\FilterTeamCommissionRuleAction;
use App\Actions\Commission\SaveTeamCommissionRuleAction;
use App\Enums\CommercialRole;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commission\IndexTeamCommissionRuleRequest;
use App\Http\Requests\Commission\SaveTeamCommissionRuleRequest;
use App\Http\Requests\Commission\ViewTeamCommissionOptionsRequest;
use App\Http\Resources\Commission\TeamCommissionRuleResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TeamCommissionRuleController extends Controller
{
    public function index(
        IndexTeamCommissionRuleRequest $request,
        string $tenantId,
        FilterTeamCommissionRuleAction $action
    ): AnonymousResourceCollection {
        $tenant = $request->attributes->get('active_tenant');

        return TeamCommissionRuleResource::collection(
            $action->execute($tenant->id, $request->validated())
        )->additional([
            'meta' => [
                'can_manage' => $request->user()->can('manageTeamCommissions', $tenant),
            ],
        ]);
    }

    public function options(ViewTeamCommissionOptionsRequest $request, string $tenantId): array
    {
        return ['data' => [
            'order_types' => OrderType::options(),
            'roles' => array_map(
                fn (CommercialRole $role): array => [
                    'value' => $role->value,
                    'label' => $role->label(),
                ],
                CommercialRole::cases(),
            ),
        ]];
    }

    public function store(
        SaveTeamCommissionRuleRequest $request,
        string $tenantId,
        SaveTeamCommissionRuleAction $action
    ): TeamCommissionRuleResource {
        return new TeamCommissionRuleResource($action->execute(
            $request->attributes->get('active_tenant')->id,
            $request->validated()
        ));
    }

    public function update(
        SaveTeamCommissionRuleRequest $request,
        string $tenantId,
        string $ruleId,
        SaveTeamCommissionRuleAction $action
    ): TeamCommissionRuleResource {
        return new TeamCommissionRuleResource($action->execute(
            $request->attributes->get('active_tenant')->id,
            $request->validated(),
            $ruleId
        ));
    }
}
