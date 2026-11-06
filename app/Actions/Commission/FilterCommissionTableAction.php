<?php

namespace App\Actions\Commission;

use App\Models\CommissionTable;
use Illuminate\Database\Eloquent\Collection;

class FilterCommissionTableAction
{
    /** @param array<string, mixed> $filters */
    public function execute(string $tenantId, array $filters): Collection
    {
        return CommissionTable::query()->where('tenant_id', $tenantId)->filter($filters)->with('operator:id,name')->orderBy('order_type')->orderBy('id')->get();
    }
}
