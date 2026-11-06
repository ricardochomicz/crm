<?php

namespace App\Actions\Tenant;

use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class FilterTenantMemberAction
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function execute(Tenant $tenant, array $filters = []): LengthAwarePaginator
    {
        return $tenant->users()
            ->when($filters['search'] ?? null, fn (Builder $q, string $search) => $q->where(function (Builder $sub) use ($search) {
                $sub->where('users.name', 'LIKE', '%'.$search.'%')
                    ->orWhere('users.email', 'LIKE', '%'.$search.'%');
            }))
            ->when($filters['role'] ?? null, fn (Builder $q, string $role) => $q->wherePivot('role', $role))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->wherePivot('status', $status))
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->paginate((int) ($filters['per_page'] ?? 20), [
                'users.id',
                'users.name',
                'users.email',
            ])
            ->appends($filters);
    }
}
