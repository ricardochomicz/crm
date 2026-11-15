<?php

namespace App\Http\Controllers\Commercial;

use App\Actions\Commercial\UpdateOrderWorkflowAction;
use App\Actions\Commercial\ScopeVisibleCommercialOrdersAction;
use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\TenantUserRole;
use App\Models\TenantUser;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commercial\UpdateOrderWorkflowRequest;
use App\Http\Resources\Commercial\CommercialOrderResource;
use App\Models\CommercialOrder;
use Illuminate\Http\Request;

class OrderWorkflowController extends Controller
{
    public function show(Request $request, string $tenantId, string $orderId, ScopeVisibleCommercialOrdersAction $scope): CommercialOrderResource
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($request->user()->can('viewOrders', $tenant), 403);
        $query = $scope->apply(CommercialOrder::query(), $tenant, $request->user());

        return new CommercialOrderResource(
            $query
                ->with(['orderStatus:id,name', 'consultant.user:id,name', 'items', 'operator:id,name', 'product:id,name'])
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
