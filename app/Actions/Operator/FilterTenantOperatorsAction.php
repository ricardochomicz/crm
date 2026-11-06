<?php

namespace App\Actions\Operator;

use App\Models\Operator;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class FilterTenantOperatorsAction
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Operator>
     */
    public function execute(Tenant $tenant, array $filters = []): Collection
    {
        $selected = $tenant->operators()->pluck('operators.id')->all();

        $operators = Operator::query()
            ->where(function (Builder $query) use ($selected): void {
                $query->where('is_active', true)->orWhereIn('id', $selected);
            })
            ->when($filters['search'] ?? null, fn (Builder $q, string $search) => $q->where(function (Builder $sub) use ($search) {
                $sub->where('name', 'LIKE', '%'.$search.'%')
                    ->orWhere('slug', 'LIKE', '%'.$search.'%');
            }))
            ->when(isset($filters['is_active']), fn (Builder $q) => $q->where('is_active', $filters['is_active']))
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'is_active']);

        foreach ($operators as $operator) {
            $operator->selected = in_array($operator->id, $selected, true);
        }

        return $operators;
    }
}
