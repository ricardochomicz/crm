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
        return OrderStatusResource::collection(
            OrderStatus::query()->orderBy('name')->get()
        )->additional(['meta' => ['can_manage' => false]]);
    }

    public function store(SaveOrderStatusRequest $request, string $tenantId, ManageOrderStatusesAction $action): OrderStatusResource
    {
        abort(403, 'Os status dos pedidos são gerenciados pelo administrador do sistema.');
    }

    public function update(SaveOrderStatusRequest $request, string $tenantId, string $statusId, ManageOrderStatusesAction $action): OrderStatusResource
    {
        abort(403, 'Os status dos pedidos são gerenciados pelo administrador do sistema.');
    }

    public function initial(Request $request, string $tenantId, string $statusId, ManageOrderStatusesAction $action): OrderStatusResource
    {
        abort(403, 'Os status dos pedidos são gerenciados pelo administrador do sistema.');
    }

    public function destroy(Request $request, string $tenantId, string $statusId, ManageOrderStatusesAction $action): JsonResponse
    {
        abort(403, 'Os status dos pedidos são gerenciados pelo administrador do sistema.');
    }

}
