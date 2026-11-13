<?php

namespace App\Actions\Commercial;

use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\TenantUserRole;
use App\Enums\TenantUserStatus;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

/**
 * Restricts radar ownership to the authenticated member, the tenant owner,
 * or members of teams explicitly supervised by the authenticated member.
 */
class ResolveCommercialRadarMembersAction
{
    public function __construct(
        private readonly ResolveTenantPermissionsAction $permissions,
    ) {}

    /** @return list<string> */
    public function execute(Tenant $tenant, User $user, ?string $memberId = null): array
    {
        $membership = $this->permissions->membership($user, $tenant);
        abort_unless($membership, 403);

        if ($memberId === null) {
            return [$membership->id];
        }

        $ids = [$membership->id];

        if ($membership->role === TenantUserRole::Owner) {
            $ids = TenantUser::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', TenantUserStatus::Active->value)
                ->pluck('id')
                ->all();
        } elseif ($user->can('viewTeamCalendar', $tenant)) {
            $teamIds = $membership->supervisedTeams()
                ->where('tenant_id', $tenant->id)
                ->pluck('id');

            $teamMembers = TenantUser::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', TenantUserStatus::Active->value)
                ->whereIn('team_id', $teamIds)
                ->pluck('id')
                ->all();

            $ids = array_values(array_unique([...$ids, ...$teamMembers]));
        }

        if ($memberId !== null) {
            abort_unless(in_array($memberId, $ids, true), 403, 'Consultor fora do escopo autorizado.');

            return [$memberId];
        }

        return $ids;
    }
}
