<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Tenant\AcceptTenantInvitationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AcceptTenantInvitationRequest;
use Illuminate\Http\JsonResponse;

class AcceptTenantInvitationController extends Controller
{
    public function __invoke(
        AcceptTenantInvitationRequest $request,
        AcceptTenantInvitationAction $action,
    ): JsonResponse {
        $membership = $action->handle(
            $request->user(),
            $request->validated('token'),
        );

        return response()->json([
            'data' => [
                'id' => $membership->getKey(),
                'tenant_id' => $membership->tenant_id,
                'role' => $membership->role->value,
            ],
        ]);
    }
}
