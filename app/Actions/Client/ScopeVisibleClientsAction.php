<?php

namespace App\Actions\Client;

use App\Enums\TenantUserRole;
use App\Models\Client;
use App\Models\TenantUser;
use Illuminate\Database\Eloquent\Builder;

class ScopeVisibleClientsAction
{
    public function execute(Builder $query, string $tenantId, int $userId): Builder
    {
        $query->where('clients.tenant_id', $tenantId);

        $membership = TenantUser::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 'ACTIVE')
            ->first();

        if ($membership === null) {
            return $query->whereRaw('1 = 0');
        }

        if (in_array($membership->role, [TenantUserRole::Owner, TenantUserRole::Manager], true)) {
            return $query;
        }

        $supervisedTeamIds = $membership->supervisedTeams()
            ->where('tenant_id', $tenantId)
            ->select('id');

        return $query->whereHas('sellers', function (Builder $sellers) use ($membership, $tenantId, $supervisedTeamIds): void {
            $sellers->where('client_sellers.tenant_id', $tenantId)
                ->whereNull('client_sellers.ended_at')
                ->where(function (Builder $assigned) use ($membership, $tenantId, $supervisedTeamIds): void {
                    $assigned->where('client_sellers.tenant_user_id', $membership->getKey())
                        ->orWhereHas('membership', function (Builder $member) use ($tenantId, $supervisedTeamIds): void {
                            $member->where('tenant_id', $tenantId)
                                ->where('status', 'ACTIVE')
                                ->whereIn('team_id', $supervisedTeamIds);
                        });
                });
        });
    }

    public function findOrFail(string $tenantId, int $userId, string $clientId): Client
    {
        return $this->execute(Client::query(), $tenantId, $userId)->findOrFail($clientId);
    }
}
