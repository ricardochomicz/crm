<?php
namespace App\Actions\Tenant;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
class FilterTenantAction
{
    /** @param array<string, mixed> $filters */
    public function execute(array $filters): LengthAwarePaginator
    {
        return Tenant::query()->with('subscription.plan:id,code,name')->withCount('users')->filter($filters)
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->appends($filters);
    }
}
