<?php

namespace App\Actions\Commercial;

use App\Models\CommercialOrder;
use Illuminate\Database\Eloquent\Collection;

class FilterCommercialOrderAction
{
    /** @param array<string, mixed> $filters */
    public function execute(string $tenantId, array $filters): Collection
    {
        return CommercialOrder::query()->where('tenant_id', $tenantId)->filter($filters)->with(['operator:id,name', 'product:id,name', 'participants', 'orderStatus:id,name', 'consultant.user:id,name'])->orderByDesc('sold_on')->orderByDesc('created_at')->orderByDesc('id')->limit(200)->get();
    }
}
