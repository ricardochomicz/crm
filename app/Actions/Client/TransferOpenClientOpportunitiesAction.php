<?php

namespace App\Actions\Client;

use App\Models\Opportunity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TransferOpenClientOpportunitiesAction
{
    /**
     * Called inside the same transaction that changes the client's active seller.
     * The caller must hold the client row lock first.
     */
    public function execute(
        string $tenantId,
        string $clientId,
        string $fromMembershipId,
        string $toMembershipId,
        int $actorUserId,
        string $sourceType,
        string $sourceId,
        string $reason,
    ): int {
        $opportunities = Opportunity::query()
            ->where('tenant_id', $tenantId)
            ->where('client_id', $clientId)
            ->where('status', 'open')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        // Never silently reassign another consultant's opportunity.
        if ($opportunities->contains(fn (Opportunity $item) => $item->tenant_user_id !== $fromMembershipId)) {
            throw ValidationException::withMessages([
                'client_ids' => 'Há oportunidades abertas com responsável diferente do titular da carteira. Regularize antes de transferir.',
            ]);
        }

        // Orders and commissions retain their original commercial attribution.
        if ($opportunities->isNotEmpty() && DB::table('commercial_orders')
            ->where('tenant_id', $tenantId)
            ->whereIn('opportunity_id', $opportunities->pluck('id'))
            ->exists()) {
            throw ValidationException::withMessages([
                'client_ids' => 'Há oportunidade aberta vinculada a pedido. Regularize o pedido antes de transferir.',
            ]);
        }

        $now = now();
        foreach ($opportunities as $opportunity) {
            $opportunity->update(['tenant_user_id' => $toMembershipId]);
            DB::table('opportunity_ownership_transfers')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'client_id' => $clientId,
                'opportunity_id' => $opportunity->id,
                'from_tenant_user_id' => $fromMembershipId,
                'to_tenant_user_id' => $toMembershipId,
                'actor_user_id' => $actorUserId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'reason' => $reason,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $opportunities->count();
    }
}
