<?php

namespace App\Actions\Opportunity;

use App\Models\Client;
use App\Models\ClientSeller;
use Illuminate\Validation\ValidationException;

class AssertClientPortfolioOwnershipAction
{
    /**
     * Must be called inside the same transaction as the opportunity write.
     * Lock ordering matches the portfolio transfer approval: client first.
     */
    public function execute(string $tenantId, string $clientId, string $assigneeId): void
    {
        Client::query()->where('tenant_id', $tenantId)
            ->whereKey($clientId)->lockForUpdate()->firstOrFail();

        $owner = ClientSeller::query()->where('tenant_id', $tenantId)
            ->where('client_id', $clientId)->whereNull('ended_at')
            ->lockForUpdate()->first();

        if (! $owner || $owner->tenant_user_id !== $assigneeId) {
            throw ValidationException::withMessages([
                'client_id' => 'Este cliente não pertence à carteira do consultor responsável. Solicite a transferência de carteira e aguarde a aprovação.',
            ]);
        }
    }
}
