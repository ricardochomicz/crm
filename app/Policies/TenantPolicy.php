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

    public function viewOpportunities(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::ViewOpportunities);
    }

    public function createOpportunities(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::CreateOpportunities);
    }

    public function updateOpportunities(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::UpdateOpportunities);
    }

    public function viewCalendar(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::ViewCalendar);
    }

    public function viewTeamCalendar(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::ViewTeamCalendar);
    }

    public function createCalendarEvents(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::CreateCalendarEvents);
    }

    public function updateCalendarEvents(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::UpdateCalendarEvents);
    }

    public function deleteCalendarEvents(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::DeleteCalendarEvents);
    }

    public function viewClients(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::ViewClients);
    }

    public function createClients(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::CreateClients);
    }

    public function updateClients(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::UpdateClients);
    }

    public function deleteClients(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::DeleteClients);
    }

    public function viewCommissions(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::ViewCommissions);
    }

    public function manageCommissions(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::ManageCommissions);
    }

    public function viewCommercialGoals(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::ViewCommercialGoals)
            || $this->viewCommissions($user, $tenant);
    }

    public function manageCommercialGoals(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::ManageCommercialGoals)
            || $this->manageCommissions($user, $tenant);
    }

    public function viewTeamCommissions(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::ViewTeamCommissions)
            || $this->viewCommissions($user, $tenant);
    }

    public function manageTeamCommissions(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::ManageTeamCommissions)
            || $this->manageCommissions($user, $tenant);
    }

    public function viewOperatorClassifications(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::ViewOperatorClassifications)
            || $this->viewCommissions($user, $tenant)
            || $this->viewClients($user, $tenant);
    }

    public function viewProducts(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::ViewProducts);
    }

    public function createProducts(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::CreateProducts) || $this->manageProducts($user, $tenant);
    }

    public function updateProducts(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::UpdateProducts) || $this->manageProducts($user, $tenant);
    }

    public function managePromotions(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::ManagePromotions) || $this->manageProducts($user, $tenant);
    }

    public function manageProducts(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::ManageProducts);
    }

    public function manageTenantOperators(User $user, Tenant $tenant): bool
    {
        return $this->permissions->membership($user, $tenant)?->role === TenantUserRole::Owner;
    }

    public function viewTenantOperators(User $user, Tenant $tenant): bool
    {
        return $this->manageTenantOperators($user, $tenant)
            || $this->viewProducts($user, $tenant)
            || $this->viewCommissions($user, $tenant)
            || $this->permissions->allows($user, $tenant, TenantPermission::ViewCommercialGoals)
            || $this->permissions->allows($user, $tenant, TenantPermission::ViewTeamCommissions)
            || $this->permissions->allows($user, $tenant, TenantPermission::ViewOperatorClassifications)
            || $this->viewClients($user, $tenant);
    }

    public function manageOperatorClassifications(User $user, Tenant $tenant): bool
    {
        return $this->permissions->membership($user, $tenant)?->role === TenantUserRole::Owner
            || $this->permissions->allows($user, $tenant, TenantPermission::ManageOperatorClassifications);
    }

    public function managePermissions(User $user, Tenant $tenant): bool
    {
        return $this->permissions->membership($user, $tenant)?->role === TenantUserRole::Owner;
    }

    public function manageUnits(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::ManageUnits);
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

    public function viewMemberProfiles(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::ViewMemberProfiles);
    }

    public function updateMemberProfiles(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::UpdateMemberProfiles);
    }

    public function updateMemberRole(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::UpdateMemberRole);
    }

    public function updateMemberStatus(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::UpdateMemberStatus);
    }

    public function removeMember(User $user, Tenant $tenant): bool
    {
        return $this->permissions->allows($user, $tenant, TenantPermission::RemoveMember);
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
