<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Tenant\RemoveTenantMemberAction;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RemoveTenantMemberController extends Controller
{
    public function __invoke(Request $request, RemoveTenantMemberAction $action): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');
        $action->handle($request->user(), $tenant, (string) $request->route('membershipId'));

        return response()->noContent();
    }
}
