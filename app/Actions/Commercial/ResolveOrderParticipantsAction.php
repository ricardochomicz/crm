<?php

namespace App\Actions\Commercial;

use App\Models\CommercialParticipant;
use App\Models\TenantUser;
use Illuminate\Validation\ValidationException;

class ResolveOrderParticipantsAction
{
    /**
     * @param  array<int, array{role: string, tenant_user_id?: ?string, commercial_participant_id?: ?string}>  $participants
     * @return array<int, array{role: string, tenant_user_id: ?string, commercial_participant_id: ?string, participant_name: string}>
     */
    public function execute(string $tenantId, array $participants): array
    {
        $roles = array_column($participants, 'role');
        if (count($roles) !== count(array_unique($roles))) {
            throw ValidationException::withMessages(['participants' => 'Cada papel pode aparecer somente uma vez no pedido.']);
        }
        $resolved = [];
        foreach ($participants as $participant) {
            $role = $participant['role'];
            $internal = in_array($role, ['CONSULTANT', 'SUPERVISOR', 'MANAGER'], true);
            if ($internal) {
                if (empty($participant['tenant_user_id']) || ! empty($participant['commercial_participant_id'])) {
                    throw ValidationException::withMessages(['participants' => 'Selecione um membro interno para consultor, supervisor ou gerente.']);
                }
                $member = TenantUser::where('tenant_id', $tenantId)->where('status', 'ACTIVE')
                    ->with('user:id,name,email')->findOrFail($participant['tenant_user_id']);
                $resolved[] = ['role' => $role, 'tenant_user_id' => $member->id, 'commercial_participant_id' => null, 'participant_name' => $member->user?->name ?? $member->user?->email ?? $member->id];
            } else {
                if (empty($participant['commercial_participant_id']) || ! empty($participant['tenant_user_id'])) {
                    throw ValidationException::withMessages(['participants' => 'Selecione um parceiro ou indicador externo.']);
                }
                $external = CommercialParticipant::where('tenant_id', $tenantId)->where('is_active', true)
                    ->where('role', $role)->findOrFail($participant['commercial_participant_id']);
                $resolved[] = ['role' => $role, 'tenant_user_id' => null, 'commercial_participant_id' => $external->id, 'participant_name' => $external->name];
            }
        }

        return $resolved;
    }
}
