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

        $sellersQuery = TenantUser::query()->where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')->with('user:id,name,email');

        if (! $membership || ! in_array($membership->role, [TenantUserRole::Owner, TenantUserRole::Manager], true)) {
            $teamIds = $membership?->supervisedTeams()->where('tenant_id', $tenantId)->select('id');
            $sellersQuery->where(function ($query) use ($membership, $teamIds): void {
                $query->where('id', $membership?->getKey());
                if ($teamIds !== null) {
                    $query->orWhereIn('team_id', $teamIds);
                }
            });
        }

        $sellers = $sellersQuery->get()
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
