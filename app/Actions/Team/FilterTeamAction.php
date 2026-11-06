<?php
namespace App\Actions\Team;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
class FilterTeamAction
{
    /** @param array<string, mixed> $filters */
    public function execute(Tenant $tenant, array $filters): LengthAwarePaginator
    {
        return $tenant->teams()->with('supervisorMembership.user')->withCount('members')->filter($filters)
            ->orderBy('name')
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->appends($filters);
    }
}
