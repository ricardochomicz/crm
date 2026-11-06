<?php
namespace App\Actions\Operator;
use App\Models\Operator;
use Illuminate\Database\Eloquent\Collection;
class FilterOperatorAction
{
    /** @param array<string, mixed> $filters */
    public function execute(array $filters): Collection
    {
        return Operator::query()->filter($filters)->orderBy('name')->orderBy('id')->get(['id', 'name', 'slug', 'is_active']);
    }
}
