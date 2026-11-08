<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class TenantPermissionChangeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_role_id' => $this->tenant_role_id,
            'previous_role_state' => $this->previous_role_state === null ? null : json_decode($this->previous_role_state, true, flags: JSON_THROW_ON_ERROR),
            'role_state' => $this->role_state === null ? null : json_decode($this->role_state, true, flags: JSON_THROW_ON_ERROR),
            'actor_name' => $this->actor_name,
            'role' => $this->role,
            'version' => $this->version,
            'previous_permissions' => json_decode($this->previous_permissions, true, flags: JSON_THROW_ON_ERROR),
            'permissions' => json_decode($this->permissions, true, flags: JSON_THROW_ON_ERROR),
            'created_at' => Carbon::parse($this->created_at)->toIso8601String(),
        ];
    }
}
