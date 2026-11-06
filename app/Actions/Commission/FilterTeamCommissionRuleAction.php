<?php

namespace App\Actions\Commission;

use App\Models\TeamCommissionRule;
use Illuminate\Database\Eloquent\Collection;

class FilterTeamCommissionRuleAction
{
    /** @param array<string, mixed> $filters */
    public function execute(string $tenantId, array $filters): Collection
    {
        return TeamCommissionRule::query()->where('tenant_id', $tenantId)->filter($filters)->with('operator:id,name')->orderBy('order_type')->orderBy('commercial_role')->orderBy('id')->get();
    }
}
