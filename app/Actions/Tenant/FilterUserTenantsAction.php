<?php

namespace App\Actions\Tenant;

use App\Enums\TenantStatus;
use App\Enums\TenantUserStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class FilterUserTenantsAction
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function execute(User $user, array $filters = []): LengthAwarePaginator
    {
        return $user
            ->tenants()
            ->where('tenants.status', TenantStatus::Active->value)
            ->wherePivot('status', TenantUserStatus::Active->value)
            ->when($filters['search'] ?? null, fn (Builder $q, string $search) => $q->where('tenants.name', 'LIKE', '%'.$search.'%'))
            ->orderBy('tenants.name')
            ->orderBy('tenants.id')
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->appends($filters);
    }
}
