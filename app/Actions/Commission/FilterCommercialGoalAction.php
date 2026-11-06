<?php

namespace App\Actions\Commission;

use App\Models\CommercialGoal;
use Illuminate\Database\Eloquent\Collection;

class FilterCommercialGoalAction
{
    /** @param array<string, mixed> $filters */
    public function execute(string $tenantId, array $filters): Collection
    {
        return CommercialGoal::query()->where('tenant_id', $tenantId)->filter($filters)->with(['operator:id,name', 'tiers'])->orderBy('order_type')->orderBy('id')->get();
    }
}
