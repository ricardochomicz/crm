<?php

namespace App\Actions\Tenant;

use App\Models\TenantInvitation;

class FindTenantInvitationByTokenAction
{
    public function execute(string $token): ?TenantInvitation
    {
        return TenantInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();
    }
}
