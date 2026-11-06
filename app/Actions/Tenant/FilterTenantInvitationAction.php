<?php
namespace App\Actions\Tenant;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
class FilterTenantInvitationAction
{
    /** @param array<string, mixed> $filters */
    public function execute(Tenant $tenant, array $filters): LengthAwarePaginator
    {
        return $tenant->invitations()->with('tenantRole')->filter($filters)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(
                (int) ($filters['per_page'] ?? 20),
                [
                    'id',
                    'email',
                    'role',
                    'tenant_role_id',
                    'status',
                    'expires_at',
                    'accepted_at',
                    'revoked_at',
                    'created_at',
                ],
            )
            ->appends($filters);
    }
}
