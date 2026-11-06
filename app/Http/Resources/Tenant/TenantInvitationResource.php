<?php

namespace App\Http\Resources\Tenant;

use App\Enums\TenantInvitationStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantInvitationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
                $isPending = $this->status
                    === TenantInvitationStatus::Pending;

                return [
                    'id' => $this->getKey(),
                    'email' => $this->email,
                    'role' => $this->role->value,
                    'tenant_role_id' => $this->tenant_role_id,
                    'role_name' => $this->tenantRole?->name,
                    'status' => $this->status->value,
                    'is_expired' => $isPending
                        && $this->expires_at->lessThanOrEqualTo(now()),
                    'reserves_seat' => $isPending
                        && $this->expires_at->greaterThan(now()),
                    'expires_at' => $this->expires_at
                        ->toISOString(),
                    'accepted_at' => $this->accepted_at
                        ?->toISOString(),
                    'revoked_at' => $this->revoked_at
                        ?->toISOString(),
                    'created_at' => $this->created_at
                        ->toISOString(),
                ];
    }
}
