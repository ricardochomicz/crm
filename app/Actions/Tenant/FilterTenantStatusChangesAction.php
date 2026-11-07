<?php

namespace App\Actions\Tenant;

use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class FilterTenantStatusChangesAction
{
    /** @param array<string, mixed> $filters */
    public function execute(Tenant $tenant, array $filters): LengthAwarePaginator
    {
        return DB::table('tenant_status_changes as changes')
            ->join('users as actors', 'actors.id', '=', 'changes.actor_user_id')
            ->where('changes.tenant_id', $tenant->getKey())
            ->select([
                'changes.id',
                'changes.tenant_id',
                'changes.previous_status',
                'changes.new_status',
                'changes.reason',
                'changes.created_at',
                'changes.actor_user_id',
                'actors.name as actor_name',
            ])
            ->orderByDesc('changes.created_at')
            ->orderByDesc('changes.id')
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->appends($filters);
    }
}
