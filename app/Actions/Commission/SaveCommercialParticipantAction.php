<?php

namespace App\Actions\Commission;

use App\Models\CommercialParticipant;

class SaveCommercialParticipantAction
{
    /** @param array<string, mixed> $data */
    public function execute(string $tenantId, array $data, ?string $participantId = null): CommercialParticipant
    {
        $participant = $participantId
            ? CommercialParticipant::query()->where('tenant_id', $tenantId)->findOrFail($participantId)
            : new CommercialParticipant(['tenant_id' => $tenantId]);

        $participant->fill($data);
        $participant->save();

        return $participant;
    }
}
