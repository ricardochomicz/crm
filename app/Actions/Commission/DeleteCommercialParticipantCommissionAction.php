<?php

namespace App\Actions\Commission;

use App\Models\CommercialParticipant;

class DeleteCommercialParticipantCommissionAction
{
    public function execute(string $tenantId, string $participantId, string $overrideId): void
    {
        $participant = CommercialParticipant::query()->where('tenant_id', $tenantId)->findOrFail($participantId);

        $participant->commissions()->where('tenant_id', $tenantId)->findOrFail($overrideId)->delete();
    }
}
