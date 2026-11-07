<?php

namespace App\Http\Controllers\Tenant\Invitations;

use App\Actions\Tenant\FindTenantInvitationByTokenAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\PublicTenantInvitationResource;
use Illuminate\Http\JsonResponse;

class ShowTenantInvitationController extends Controller
{
    public function __invoke(
        string $token,
        FindTenantInvitationByTokenAction $action
    ): JsonResponse {
        $invitation = $action->execute($token);
        $isAvailable = $invitation?->isAvailable() ?? false;

        return (new PublicTenantInvitationResource($invitation))
            ->response()
            ->setStatusCode($isAvailable ? 200 : 410)
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
