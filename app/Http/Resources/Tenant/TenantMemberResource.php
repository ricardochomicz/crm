<?php

namespace App\Http\Resources\Tenant;

use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\TenantUserRole;
use App\Enums\TenantPermission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantMemberResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $permissions = app(ResolveTenantPermissionsAction::class);
        $tenant = $request->attributes->get('active_tenant');
        $actor = $permissions->membership($request->user(), $tenant);
        $canManageProfile = $actor !== null
            && in_array($actor->role, [TenantUserRole::Owner, TenantUserRole::Manager], true)
            && $permissions->canManageMember($request->user(), $tenant, $this->membership);

        return [
            'id' => $this->membership->id,
            'user_id' => $this->id,
            'can_view_profile' => $permissions->allows($request->user(), $tenant, TenantPermission::ViewMemberProfiles)
                && ($this->id === $request->user()->getKey() || $canManageProfile),
            'can_edit_profile' => $permissions->allows($request->user(), $tenant, TenantPermission::UpdateMemberProfiles)
                && ($this->id === $request->user()->getKey() || $canManageProfile),
            'can_manage' => app(ResolveTenantPermissionsAction::class)->canManageMember(
                $request->user(), $request->attributes->get('active_tenant'), $this->membership,
            ),
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->membership->role->value,
            'tenant_role_id' => $this->membership->tenant_role_id,
            'role_name' => $this->membership->tenantRole?->name,
            'status' => $this->membership->status->value,
            'joined_at' => $this->membership->joined_at?->toIso8601String(),
        ];
    }
}
