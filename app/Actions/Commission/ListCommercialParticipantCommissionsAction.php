<?php

namespace App\Actions\Commission;

use App\Models\CommercialParticipant;
use App\Models\TeamCommissionRule;

class ListCommercialParticipantCommissionsAction
{
    public function execute(string $tenantId, string $participantId): array
    {
        $participant = CommercialParticipant::query()->where('tenant_id', $tenantId)->findOrFail($participantId);

        return [
            'defaults' => TeamCommissionRule::query()
                ->where('tenant_id', $tenantId)
                ->where('commercial_role', $participant->role)
                ->where('is_active', true)
                ->with('operator:id,name')
                ->get(),
            'overrides' => $participant->commissions()
                ->where('tenant_id', $tenantId)
                ->with('operator:id,name')
                ->orderBy('order_type')
                ->get(),
        ];
    }
}
