<?php

namespace App\Actions\Client;

use App\Models\Client;

class DeleteClientAction
{
    public function execute(string $tenantId, string $clientId): void
    {
        Client::query()->where('tenant_id', $tenantId)->findOrFail($clientId)->delete();
    }
}
