<?php

namespace App\Http\Resources\Tenant;

use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\TenantUserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantRoleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'legacy_role' => $this->legacy_role,
            'permissions' => $this->effectivePermissions(),
            'is_system' => $this->is_system,
            'is_active' => $this->is_active,
            'version' => $this->version,
            'can_assign' => app(ResolveTenantPermissionsAction::class)->canAssign(
                $request->user(),
                $request->attributes->get('active_tenant'),
                TenantUserRole::from($this->legacy_role),
                $this->resource,
            ),
        ];
    }
}
