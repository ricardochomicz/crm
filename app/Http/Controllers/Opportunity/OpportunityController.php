<?php

namespace App\Http\Controllers\Opportunity;

use App\Actions\Opportunity\OpportunityAction;
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

    public function show(OpportunityRequest $request, string $tenantId, string $opportunityId, OpportunityAction $action): JsonResponse
    {
        return response()->json(['data' => $action->find($this->tenant($request), $request->user(), $opportunityId)]);
    }

    public function store(OpportunityRequest $request, string $tenantId, OpportunityAction $action): JsonResponse
    {
        return response()->json(['data' => $action->create($this->tenant($request), $request->user(), $request->validated())], 201);
    }

    public function update(OpportunityRequest $request, string $tenantId, string $opportunityId, OpportunityAction $action): JsonResponse
    {
        return response()->json(['data' => $action->update($this->tenant($request), $request->user(), $opportunityId, $request->validated())]);
    }
}
