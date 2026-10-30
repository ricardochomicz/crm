<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Tenant\CreateTenantMemberAction;
use App\Http\Controllers\Controller;
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
            $request->user('web'),
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
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'meta' => [
                'password_link_sent' => $linkSent,
            ],
        ], 201);
    }
}
