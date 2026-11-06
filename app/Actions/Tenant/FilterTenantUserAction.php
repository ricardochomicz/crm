<?php
namespace App\Actions\Tenant;
use App\Models\Tenant;
use App\Models\Team;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
class FilterTenantUserAction
{
    /** @param array<string, mixed> $filters */
    public function execute(Team $team, array $filters): LengthAwarePaginator
    {
        return $team->members()->where('tenant_id', $team->tenant_id)->with('user:id,name,email')->filter($filters)
            ->orderBy('joined_at')
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->appends($filters);
    }
}
