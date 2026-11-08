<?php

namespace App\Http\Resources\Tenant;

use App\Actions\Tenant\ResolveTenantPermissionsAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantMemberResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->membership->id,
            'user_id' => $this->id,
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
