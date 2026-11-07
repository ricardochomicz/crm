<?php

namespace App\Http\Controllers\OrderStatus;

use App\Actions\OrderStatus\ManageOrderStatusesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrderStatus\SaveOrderStatusRequest;
use App\Http\Resources\OrderStatus\OrderStatusResource;
use App\Models\OrderStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderStatusController extends Controller
{
    public function index(Request $request, string $tenantId, ManageOrderStatusesAction $action)
    {
        $tenant = $request->attributes->get('active_tenant');
        $membership = $tenant->users()->where('users.id', $request->user()->id)->first();

        return OrderStatusResource::collection(
            OrderStatus::query()->where('tenant_id', $tenant->id)->orderBy('name')->get()
        )->additional(['meta' => [
            'can_manage' => $membership && $membership->membership->role === \App\Enums\TenantUserRole::Owner,
        ]]);
    }

    public function store(SaveOrderStatusRequest $request, string $tenantId, ManageOrderStatusesAction $action): OrderStatusResource
    {
        $tenant = $request->attributes->get('active_tenant');
        $action->authorizeManager($tenant, $request->user()->id);

        return new OrderStatusResource($action->save($tenant->id, $request->validated('name')));
    }

    public function update(SaveOrderStatusRequest $request, string $tenantId, string $statusId, ManageOrderStatusesAction $action): OrderStatusResource
    {
        $tenant = $request->attributes->get('active_tenant');
        $action->authorizeManager($tenant, $request->user()->id);

        return new OrderStatusResource($action->save($tenant->id, $request->validated('name'), $statusId));
    }

    public function destroy(Request $request, string $tenantId, string $statusId, ManageOrderStatusesAction $action): JsonResponse
    {
        $tenant = $request->attributes->get('active_tenant');
        $action->authorizeManager($tenant, $request->user()->id);
        $action->delete($tenant->id, $statusId);

        return response()->json(['message' => 'Status excluído.']);
    }
}
