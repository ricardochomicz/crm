<?php
namespace App\Http\Resources\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class CreatedTenantInvitationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
                'id' => $this->getKey(),
                'email' => $this->email,
                'role' => $this->role->value,
                'tenant_role_id' => $this->tenant_role_id,
                'status' => $this->status->value,
                'expires_at' => $this->expires_at->toISOString(),
            ];
    }
}
