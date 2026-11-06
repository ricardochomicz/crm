<?php

namespace App\Actions\Commission;

use App\Models\CommercialParticipant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FilterCommercialParticipantAction
{
    /** @param array<string, mixed> $filters */
    public function execute(string $tenantId, array $filters): LengthAwarePaginator
    {
        return CommercialParticipant::query()->where('tenant_id', $tenantId)->filter($filters)->orderBy('name')->orderBy('id')->paginate(15);
    }
}
