<?php

namespace App\Actions\Tenant;

use App\Models\Tenant;
use App\Models\TenantUnit;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class FilterTenantUnitAction
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, TenantUnit>|LengthAwarePaginator
     */
    public function execute(Tenant $tenant, array $filters = [], ?User $user = null, bool $canManage = true): Collection|LengthAwarePaginator
    {
        $query = $tenant->units()
            ->filter($filters)
            ->withCount(['members', 'teams']);

        if (! $canManage && $user !== null) {
            $query->whereHas('members', fn (Builder $members): Builder => $members->where('user_id', $user->id));
        }

        $query->orderByDesc('is_headquarters')->orderBy('name')->orderBy('id');

        if (isset($filters['per_page'])) {
            return $query->paginate((int) $filters['per_page'])->appends($filters);
        }

        return $query->get();
    }
}
