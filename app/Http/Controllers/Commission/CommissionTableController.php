<?php

namespace App\Http\Controllers\Commission;

use App\Actions\Commission\FilterCommissionTableAction;
use App\Actions\Commission\ManageCommissionSettingsAction;
use App\Actions\Commission\SaveCommissionTableAction;
use App\Models\OrderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commission\IndexCommissionTableRequest;
use App\Http\Requests\Commission\SaveCommissionSettingsRequest;
use App\Http\Requests\Commission\SaveCommissionTableRequest;
use App\Http\Requests\Commission\ViewCommissionSettingsRequest;
use App\Http\Resources\Commission\CommissionTableResource;

class CommissionTableController extends Controller
{
    public function index(
        IndexCommissionTableRequest $request,
        string $tenantId,
        FilterCommissionTableAction $action
    ): array {
        $tenant = $request->attributes->get('active_tenant');

        return [
            'data' => CommissionTableResource::collection(
                $action->execute($tenant->id, $request->validated())
            ),
            'meta' => [
                'can_manage' => $request->user()->can('manageCommissions', $tenant),
            ],
        ];
    }

    public function orderTypes(ViewCommissionSettingsRequest $request, string $tenantId): array
    {
        return ['data' => OrderType::query()->where('is_active', true)->orderBy('position')->orderBy('name')->get(['id', 'name'])->map(fn (OrderType $type): array => ['value' => $type->id, 'label' => $type->name])->all()];
    }

    public function settings(
        ViewCommissionSettingsRequest $request,
        string $tenantId,
        ManageCommissionSettingsAction $action
    ): array {
        return ['data' => $action->show($request->attributes->get('active_tenant')->id)];
    }

    public function updateSettings(
        SaveCommissionSettingsRequest $request,
        string $tenantId,
        ManageCommissionSettingsAction $action
    ): array {
        return ['data' => $action->update(
            $request->attributes->get('active_tenant')->id,
            $request->validated()
        )];
    }

    public function store(
        SaveCommissionTableRequest $request,
        string $tenantId,
        SaveCommissionTableAction $action
    ): array {
        return ['data' => new CommissionTableResource($action->execute(
            $request->attributes->get('active_tenant')->id,
            $request->validated()
        ))];
    }

    public function update(
        SaveCommissionTableRequest $request,
        string $tenantId,
        string $commissionId,
        SaveCommissionTableAction $action
    ): array {
        return ['data' => new CommissionTableResource($action->execute(
            $request->attributes->get('active_tenant')->id,
            $request->validated(),
            $commissionId
        ))];
    }
}
