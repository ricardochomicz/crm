<?php

namespace App\Http\Controllers\Tenant\Members;

use App\Actions\Tenant\FindTenantMemberProfileAction;
use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Actions\Tenant\UpdateTenantMemberProfileAction;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTenantMemberProfileRequest;
use App\Http\Resources\Tenant\TenantMemberProfileResource;
use App\Models\Tenant;

class UpdateTenantMemberProfileController extends Controller
{
    public function __invoke(
        UpdateTenantMemberProfileRequest $request,
        string $tenantId,
        string $membershipId,
        FindTenantMemberProfileAction $finder,
        UpdateTenantMemberProfileAction $action
    ): TenantMemberProfileResource {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        $member = $finder->execute($tenant, $membershipId);
        $permissions = app(ResolveTenantPermissionsAction::class);
        $actor = $permissions->membership($request->user(), $tenant);
        $isSelf = $member->user_id === $request->user()->getKey();
        $canEditOthers = $actor !== null
            && in_array($actor->role, [TenantUserRole::Owner, TenantUserRole::Manager], true)
            && $permissions->canManageMember($request->user(), $tenant, $member);

        abort_unless($isSelf || $canEditOthers, 403, 'Você só pode editar seu próprio cadastro.');

        return new TenantMemberProfileResource(
            $action->execute($member, $request->validated())
        );
    }
}
