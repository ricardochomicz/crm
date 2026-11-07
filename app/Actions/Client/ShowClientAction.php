<?php

namespace App\Actions\Client;

use App\Models\Client;

class ShowClientAction
{
    public function execute(string $tenantId, string $clientId): Client
    {
        return Client::query()->where('tenant_id', $tenantId)
            ->with([
                'operator:id,name',
                'classification:id,code,name',
                'contacts',
                'sellers' => fn ($query) => $query->with('membership')->orderByDesc('started_at'),
                'operatorHistory' => fn ($query) => $query
                    ->with(['operator:id,name', 'classification:id,code,name'])
                    ->orderByDesc('changed_at'),
            ])->findOrFail($clientId);
    }
}
