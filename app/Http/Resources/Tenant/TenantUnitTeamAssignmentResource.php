<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantUnitTeamAssignmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'unit_name' => $this->unit?->name,
            'members_count' => $this->members_count,
            'removal_blocked_reason' => $this->resource->unitAssignmentRemovalBlockedReason ?? null,
        ];
    }
}
