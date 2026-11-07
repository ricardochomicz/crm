<?php

namespace App\Actions\Tenant;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class FilterTenantPermissionChangesAction
{
    /** @param array<string, mixed> $filters */
    public function execute(string $tenantId, array $filters): LengthAwarePaginator
    {
        return DB::table('tenant_permission_changes as changes')
            ->join('users', 'users.id', '=', 'changes.actor_user_id')
            ->where('changes.tenant_id', $tenantId)
            ->when(isset($filters['role']), fn ($query) => $query->where('changes.role', $filters['role']))
            ->orderByDesc('changes.created_at')
            ->orderByDesc('changes.id')
            ->paginate(20, ['changes.*', 'users.name as actor_name'])
            ->appends($filters);
    }
}
