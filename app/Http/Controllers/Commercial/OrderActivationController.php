<?php

namespace App\Http\Controllers\Commercial;

use App\Actions\Commercial\ActivateOrderItemsAction;
use App\Actions\Commercial\CompleteOrderAction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commercial\ActivateOrderItemsRequest;
use App\Http\Resources\Commercial\CommercialOrderResource;

class OrderActivationController extends Controller
{
    public function store(ActivateOrderItemsRequest $request, string $tenantId, string $orderId, ActivateOrderItemsAction $action): CommercialOrderResource
    {
        return new CommercialOrderResource($action->execute(
            $request->attributes->get('active_tenant'),
            $request->user(),
            $orderId,
            $request->validated()
        ));
    }
    public function complete(Request $request, string $tenantId, string $orderId, CompleteOrderAction $action): CommercialOrderResource
    {
        return new CommercialOrderResource($action->execute(
            $request->attributes->get('active_tenant'), $request->user(), $orderId
        ));
    }
}
