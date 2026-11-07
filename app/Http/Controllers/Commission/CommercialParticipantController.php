<?php

namespace App\Http\Controllers\Commission;

use App\Actions\Commission\DeleteCommercialParticipantCommissionAction;
use App\Actions\Commission\FilterCommercialParticipantAction;
use App\Actions\Commission\ListCommercialParticipantCommissionsAction;
use App\Actions\Commission\SaveCommercialParticipantAction;
use App\Actions\Commission\SaveCommercialParticipantCommissionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commission\DeleteCommercialParticipantCommissionRequest;
use App\Http\Requests\Commission\IndexCommercialParticipantRequest;
use App\Http\Requests\Commission\SaveCommercialParticipantCommissionRequest;
use App\Http\Requests\Commission\SaveCommercialParticipantRequest;
use App\Http\Requests\Commission\ViewCommercialParticipantCommissionsRequest;
use App\Http\Resources\Commission\CommercialParticipantCommissionResource;
use App\Http\Resources\Commission\CommercialParticipantResource;
use App\Http\Resources\Commission\TeamCommissionRuleResource;

class CommercialParticipantController extends Controller
{
    public function index(
        IndexCommercialParticipantRequest $request,
        string $tenantId,
        FilterCommercialParticipantAction $action
    ): array {
        $tenant = $request->attributes->get('active_tenant');
        $page = $action->execute($tenant->id, $request->validated());

        return [
            'data' => CommercialParticipantResource::collection($page->getCollection()),
            'meta' => [
                'can_manage' => $request->user()->can('manageCommissions', $tenant),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ];
    }

    public function store(
        SaveCommercialParticipantRequest $request,
        string $tenantId,
        SaveCommercialParticipantAction $action
    ): array {
        return ['data' => new CommercialParticipantResource($action->execute(
            $request->attributes->get('active_tenant')->id,
            $request->validated()
        ))];
    }

    public function update(
        SaveCommercialParticipantRequest $request,
        string $tenantId,
        string $participantId,
        SaveCommercialParticipantAction $action
    ): array {
        return ['data' => new CommercialParticipantResource($action->execute(
            $request->attributes->get('active_tenant')->id,
            $request->validated(),
            $participantId
        ))];
    }

    public function commissions(
        ViewCommercialParticipantCommissionsRequest $request,
        string $tenantId,
        string $participantId,
        ListCommercialParticipantCommissionsAction $action
    ): array {
        $commissions = $action->execute($request->attributes->get('active_tenant')->id, $participantId);

        return ['data' => [
            'defaults' => TeamCommissionRuleResource::collection($commissions['defaults']),
            'overrides' => CommercialParticipantCommissionResource::collection($commissions['overrides']),
        ]];
    }

    public function saveCommission(
        SaveCommercialParticipantCommissionRequest $request,
        string $tenantId,
        string $participantId,
        SaveCommercialParticipantCommissionAction $action,
        ?string $overrideId = null
    ): array {
        return ['data' => new CommercialParticipantCommissionResource($action->execute(
            $request->attributes->get('active_tenant')->id,
            $participantId,
            $request->validated(),
            $overrideId
        ))];
    }

    public function deleteCommission(
        DeleteCommercialParticipantCommissionRequest $request,
        string $tenantId,
        string $participantId,
        string $overrideId,
        DeleteCommercialParticipantCommissionAction $action
    ): array {
        $action->execute($request->attributes->get('active_tenant')->id, $participantId, $overrideId);

        return ['data' => ['deleted' => true]];
    }
}
