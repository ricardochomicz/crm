<?php

namespace App\Actions\Commission;

use App\Models\CommercialParticipant;
use App\Models\CommercialParticipantCommission;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveCommercialParticipantCommissionAction
{
    /** @param array<string, mixed> $data */
    public function execute(string $tenantId, string $participantId, array $data, ?string $overrideId = null): CommercialParticipantCommission
    {
        return DB::transaction(function () use ($tenantId, $participantId, $data, $overrideId) {
            $participant = CommercialParticipant::query()->where('tenant_id', $tenantId)
                ->lockForUpdate()->findOrFail($participantId);

            $query = CommercialParticipantCommission::query()
                ->where('tenant_id', $tenantId)
                ->where('commercial_participant_id', $participant->id);

            $record = $overrideId ? (clone $query)->lockForUpdate()->findOrFail($overrideId) : null;

            if ($data['is_active']) {
                $overlap = (clone $query)
                    ->where('operator_id', $data['operator_id'])
                    ->where('order_type', $data['order_type'])
                    ->where('is_active', true)
                    ->where('starts_on', '<=', $data['ends_on'] ?? '9999-12-31')
                    ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $data['starts_on']));

                if ($record) {
                    $overlap->where('id', '!=', $record->id);
                }

                if ($overlap->exists()) {
                    throw ValidationException::withMessages([
                        'starts_on' => 'Já existe uma exceção ativa com vigência sobreposta.',
                    ]);
                }
            }

            if ($record) {
                $record->update($data);
            } else {
                $record = CommercialParticipantCommission::create([
                    'tenant_id' => $tenantId,
                    'commercial_participant_id' => $participant->id,
                    ...$data,
                ]);
            }

            return $record->fresh('operator:id,name');
        });
    }
}
