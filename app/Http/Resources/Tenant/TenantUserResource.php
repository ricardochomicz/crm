<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
                'id' => $this->id,
                'user_id' => $this->user_id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
                'role' => $this->role->value,
                'role_name' => $this->tenantRole?->name,
                'status' => $this->status->value,
                'team_id' => $this->team_id,
                'joined_at' => $this->joined_at?->toIso8601String(),
            ];
    }
}
