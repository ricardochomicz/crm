<?php

namespace App\Actions\Client;

use App\Enums\TenantUserRole;
use App\Models\TenantUser;

class ListClientOptionsAction
{
    public function execute(string $tenantId, int $userId): array
    {
        $membership = TenantUser::query()->where('tenant_id', $tenantId)
            ->where('user_id', $userId)->where('status', 'ACTIVE')->first();

        $canAssign = $membership && (
            in_array($membership->role, [TenantUserRole::Owner, TenantUserRole::Manager], true)
            || $membership->supervisedTeams()->exists()
        );

        $sellers = TenantUser::query()->where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')->with('user:id,name,email')->get()
            ->map(fn ($member) => [
                'id' => $member->id,
                'name' => $member->user?->name ?? $member->user?->email ?? $member->id,
            ])->values();

        return [
            'sellers' => $sellers,
            'current_seller_id' => $membership?->id,
            'can_assign_seller' => (bool) $canAssign,
        ];
    }
}
