<?php

namespace App\Http\Controllers\Tenant\Members;

use App\Actions\Tenant\FindTenantMemberProfileAction;
use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ShowTenantMemberProfileRequest;
use App\Http\Resources\Tenant\TenantMemberProfileResource;
use App\Models\Tenant;

class ShowTenantMemberProfileController extends Controller
{
    public function __invoke(
        ShowTenantMemberProfileRequest $request,
        string $tenantId,
        string $membershipId,
        FindTenantMemberProfileAction $action
    ): TenantMemberProfileResource {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        $member = $action->execute($tenant, $membershipId);
        $permissions = app(ResolveTenantPermissionsAction::class);
        $actor = $permissions->membership($request->user(), $tenant);
        $isSelf = $member->user_id === $request->user()->getKey();
        $canViewOthers = $actor !== null
            && in_array($actor->role, [TenantUserRole::Owner, TenantUserRole::Manager], true)
            && $permissions->canManageMember($request->user(), $tenant, $member);

        abort_unless($isSelf || $canViewOthers, 403, 'Você só pode consultar seu próprio cadastro.');

        return new TenantMemberProfileResource($member);
    }
}
