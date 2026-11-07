<?php

namespace App\Http\Controllers\Commission;

use App\Actions\Commission\FilterCommercialGoalAction;
use App\Actions\Commission\SaveCommercialGoalAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commission\IndexCommercialGoalRequest;
use App\Http\Requests\Commission\SaveCommercialGoalRequest;
use App\Http\Resources\Commission\CommercialGoalResource;

class CommercialGoalController extends Controller
{
    public function index(
        IndexCommercialGoalRequest $request,
        string $tenantId,
        FilterCommercialGoalAction $action
    ): array {
        $tenant = $request->attributes->get('active_tenant');

        return [
            'data' => CommercialGoalResource::collection(
                $action->execute($tenant->id, $request->validated())
            ),
            'meta' => [
                'can_manage' => $request->user()->can('manageCommissions', $tenant),
            ],
        ];
    }

    public function store(
        SaveCommercialGoalRequest $request,
        string $tenantId,
        SaveCommercialGoalAction $action
    ): array {
        return ['data' => new CommercialGoalResource($action->execute(
            $request->attributes->get('active_tenant')->id,
            $request->validated()
        ))];
    }

    public function update(
        SaveCommercialGoalRequest $request,
        string $tenantId,
        string $goalId,
        SaveCommercialGoalAction $action
    ): array {
        return ['data' => new CommercialGoalResource($action->execute(
            $request->attributes->get('active_tenant')->id,
            $request->validated(),
            $goalId
        ))];
    }
}
