<?php

namespace App\Http\Controllers\Tenant\Members;

use App\Actions\Tenant\CreateTenantMemberAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\CreatedTenantMemberResource;
use App\Http\Requests\Tenant\StoreTenantMemberRequest;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Throwable;

class StoreTenantMemberController extends Controller
{
    public function __invoke(
        StoreTenantMemberRequest $request,
        CreateTenantMemberAction $createTenantMemberAction,
    ): JsonResponse {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        $user = $createTenantMemberAction->handle(
            $request->user(),
            $tenant,
            $request->safe()->only(['name', 'email', 'role']),
        );

        $linkSent = false;

        try {
            $linkSent = Password::broker('users')->sendResetLink([
                'email' => $user->email,
            ]) === Password::RESET_LINK_SENT;
        } catch (Throwable $exception) {
            report($exception);
        }

        return response()->json([
            'data' => new CreatedTenantMemberResource($user),
            'meta' => [
                'password_link_sent' => $linkSent,
            ],
        ], 201);
    }
}
