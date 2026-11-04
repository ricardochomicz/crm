<?php

namespace App\Policies;

use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\TenantPermission;
use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function __construct(private ResolveTenantPermissionsAction $permissions) {}

    public function managePermissions(User $user, Tenant $tenant): bool
    {
        return $this->permissions->membership($user, $tenant)?->role === TenantUserRole::Owner;
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::UpdateCompany);
    }

    public function addMember(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::CreateMembers);
    }

    public function createInvitation(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::CreateInvitations);
    }

    public function viewMembers(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::ViewMembers);
    }

    public function createTeam(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::CreateTeams);
    }

    public function viewSubscription(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::ViewSubscription);
    }

    public function updateMemberRole(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::UpdateMemberRole);
    }

    public function updateMemberStatus(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::UpdateMemberStatus);
    }

    public function viewTeams(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::ViewTeams);
    }

    public function updateTeamSupervisor(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::UpdateSupervisor);
    }

    public function updateTeam(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::UpdateTeams);
    }

    public function updateMemberTeam(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::UpdateMemberTeam);
    }

    public function viewInvitations(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::ViewInvitations);
    }

    public function revokeInvitation(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::RevokeInvitations);
    }

    public function resendInvitation(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::ResendInvitations);
    }
}
