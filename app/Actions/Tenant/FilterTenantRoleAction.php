<?php

namespace App\Actions\Tenant;

use App\Models\Tenant;
use App\Models\TenantRole;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class FilterTenantRoleAction
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, TenantRole>|LengthAwarePaginator
     */
    public function execute(Tenant $tenant, array $filters = []): Collection|LengthAwarePaginator
    {
        $query = TenantRole::query()
            ->where('tenant_id', $tenant->id)
            ->filter($filters)
            ->orderBy('name')
            ->orderBy('id');

        if (isset($filters['per_page'])) {
            return $query->paginate((int) $filters['per_page'])->appends($filters);
        }

        return $query->get();
    }
}
