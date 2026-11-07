<?php

namespace App\Actions\Client;

use App\Models\Client;

class DeleteClientAction
{
    public function __construct(private ScopeVisibleClientsAction $visibility) {}

    public function execute(string $tenantId, string $clientId, int $userId): void
    {
        $this->visibility->findOrFail($tenantId, $userId, $clientId)->delete();
    }
}
