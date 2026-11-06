<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Tenant\SaveTenantRoleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SaveTenantRoleRequest;
use App\Http\Resources\Tenant\TenantRoleResource;
use App\Models\TenantRole;
use Illuminate\Http\Request;
class TenantRoleController extends Controller {
    public function index(Request $request,string $tenantId) {
        $tenant=$request->attributes->get('active_tenant');
        abort_unless($request->user()->can('viewMembers',$tenant)||$request->user()->can('managePermissions',$tenant),403);
        return TenantRoleResource::collection(TenantRole::where('tenant_id',$tenant->id)->orderBy('name')->get());
    }
    public function store(SaveTenantRoleRequest $request,string $tenantId,SaveTenantRoleAction $action):TenantRoleResource {
        return new TenantRoleResource($action->execute($request->attributes->get('active_tenant'),$request->validated()));
    }
    public function update(SaveTenantRoleRequest $request,string $tenantId,string $roleId,SaveTenantRoleAction $action):TenantRoleResource {
        return new TenantRoleResource($action->execute($request->attributes->get('active_tenant'),$request->validated(),$roleId));
    }
}
