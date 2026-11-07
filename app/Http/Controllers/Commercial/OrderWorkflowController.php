<?php

namespace App\Http\Controllers\Commercial;

use App\Actions\Commercial\UpdateOrderWorkflowAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commercial\UpdateOrderWorkflowRequest;
use App\Http\Resources\Commercial\CommercialOrderResource;
use App\Models\CommercialOrder;
use Illuminate\Http\Request;

class OrderWorkflowController extends Controller
{
    public function show(Request $request, string $tenantId, string $orderId): CommercialOrderResource
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($request->user()->can('viewCommissions', $tenant), 403);

        return new CommercialOrderResource(
            CommercialOrder::query()->where('tenant_id', $tenant->id)
                ->with(['orderStatus:id,name', 'items', 'operator:id,name', 'product:id,name'])
                ->findOrFail($orderId)
        );
    }

    public function update(UpdateOrderWorkflowRequest $request, string $tenantId, string $orderId, UpdateOrderWorkflowAction $action): CommercialOrderResource
    {
        $tenant = $request->attributes->get('active_tenant');

        return new CommercialOrderResource($action->execute(
            $tenant, $request->user(), $orderId, $request->validated()
        ));
    }
}
