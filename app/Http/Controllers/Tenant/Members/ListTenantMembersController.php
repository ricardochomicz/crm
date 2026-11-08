<?php

namespace App\Http\Controllers\Tenant\Members;

use App\Actions\Tenant\FilterTenantMemberAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ListTenantMembersRequest;
use App\Http\Resources\Tenant\TenantMemberResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListTenantMembersController extends Controller
{
    public function __invoke(
        ListTenantMembersRequest $request,
        FilterTenantMemberAction $filter
    ): AnonymousResourceCollection {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        $members = $filter->execute($tenant, $request->validated());

        $members->through(
            fn (User $user): TenantMemberResource => new TenantMemberResource($user)
        );

        return TenantMemberResource::collection($members);
    }
}
