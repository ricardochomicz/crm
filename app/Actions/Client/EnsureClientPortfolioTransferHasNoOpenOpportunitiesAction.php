<?php

namespace App\Actions\Client;

use App\Models\Opportunity;
use Illuminate\Validation\ValidationException;

class EnsureClientPortfolioTransferHasNoOpenOpportunitiesAction
{
    public function execute(string $tenantId, array $clientIds): void
    {
        if ($clientIds === []) {
            return;
        }

        $hasOpen = Opportunity::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('client_id', $clientIds)
            ->where('status', 'open')
            ->exists();

        if ($hasOpen) {
            throw ValidationException::withMessages([
                'client_ids' => 'Existem oportunidades em aberto nesta carteira. Conclua ou encerre essas oportunidades antes de transferir os clientes.',
            ]);
        }
    }
}
