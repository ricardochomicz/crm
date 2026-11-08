<?php

namespace App\Http\Controllers\Commercial;

use App\Actions\Commercial\UpdateOrderWorkflowAction;
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
    public function show(Request $request, string $tenantId, string $orderId, ResolveTenantPermissionsAction $permissions): CommercialOrderResource
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($request->user()->can('viewCommissions', $tenant) || $request->user()->can('viewOpportunities', $tenant), 403);
        $actor = $permissions->membership($request->user(), $tenant) ?? abort(403);
        $query = CommercialOrder::query()->where('tenant_id', $tenant->id);
        if (! in_array($actor->role, [TenantUserRole::Owner, TenantUserRole::Manager], true)) {
            $teams = DB::table('teams')->where('tenant_id', $tenant->id)
                ->where('supervisor_membership_id', $actor->id)->pluck('id');
            $members = TenantUser::query()->where('tenant_id', $tenant->id)
                ->whereIn('team_id', $teams)->pluck('id')->push($actor->id)->unique()->all();
            $query->whereIn('tenant_user_id', $members);
        }

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
