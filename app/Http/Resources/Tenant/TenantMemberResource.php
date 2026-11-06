<?php
namespace App\Http\Resources\Tenant;
use App\Models\User;
use App\Enums\TenantUserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class TenantMemberResource extends JsonResource
{
    /** @param array<int, TenantUserRole> $assignableRoles */
    public function __construct(User $resource, private array $assignableRoles)
    {
        parent::__construct($resource);
    }
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
                'id' => $this->membership->id,
                'user_id' => $this->id,
                'can_manage' => $this->id !== $request->user()->id && in_array($this->membership->role, $this->assignableRoles, true),
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
