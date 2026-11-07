<?php

namespace App\Http\Controllers\Opportunity;

use App\Actions\Opportunity\OpportunityAction;
use App\Actions\Opportunity\ConfirmOpportunitySaleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Opportunity\OpportunityRequest;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class OpportunityController extends Controller
{
    private function tenant(OpportunityRequest $request): Tenant
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($tenant instanceof Tenant, 403);
        return $tenant;
    }

    public function index(OpportunityRequest $request, string $tenantId, OpportunityAction $action): JsonResponse
    {
        return response()->json($action->list($this->tenant($request), $request->user(), $request->validated()));
    }

    public function overview(OpportunityRequest $request, string $tenantId, OpportunityAction $action): JsonResponse
    {
        return response()->json(['data' => $action->overview($this->tenant($request), $request->user(), $request->validated())]);
    }

    public function stages(OpportunityRequest $request, string $tenantId, OpportunityAction $action): JsonResponse
    {
        return response()->json(['data' => $action->stages($this->tenant($request), $request->user())]);
    }

    public function moveStage(OpportunityRequest $request, string $tenantId, string $opportunityId, OpportunityAction $action): JsonResponse
    {
        $data = $request->validate(['funnel_stage_id' => ['required', 'uuid']]);
        return response()->json(['data' => $action->moveStage($this->tenant($request), $request->user(), $opportunityId, $data['funnel_stage_id'])]);
    }

    public function show(OpportunityRequest $request, string $tenantId, string $opportunityId, OpportunityAction $action): JsonResponse
    {
        return response()->json(['data' => $action->find($this->tenant($request), $request->user(), $opportunityId)]);
    }

    public function store(OpportunityRequest $request, string $tenantId, OpportunityAction $action): JsonResponse
    {
        return response()->json(['data' => $action->create($this->tenant($request), $request->user(), $request->validated())], 201);
    }

    public function confirmSale(OpportunityRequest $request, string $tenantId, string $opportunityId, ConfirmOpportunitySaleAction $action): JsonResponse
    {
        return response()->json(['data' => $action->execute($this->tenant($request), $request->user(), $opportunityId)], 201);
    }

    public function update(OpportunityRequest $request, string $tenantId, string $opportunityId, OpportunityAction $action): JsonResponse
    {
        return response()->json(['data' => $action->update($this->tenant($request), $request->user(), $opportunityId, $request->validated())]);
    }
}
