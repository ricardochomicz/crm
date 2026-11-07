<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantUnitMemberAssignmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $unitId = (string) $request->route('unitId');

        return [
            'id' => $this->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'status' => $this->status->value,
            'team_name' => $this->team?->tenant_unit_id === $unitId ? $this->team->name : null,
            'supervised_team_names' => $this->supervisedTeams->where('tenant_unit_id', $unitId)->pluck('name')->values()->all(),
            'removal_blocked_reason' => ($this->belongs_to_unit_team || $this->supervises_unit_team)
                ? 'Este membro integra ou supervisiona uma equipe desta unidade. Atualize a equipe antes de remover o vínculo.'
                : null,
        ];
    }
}
