<?php

namespace App\Http\Controllers\Tenant\Invitations;

use App\Http\Controllers\Controller;
use App\Models\TenantInvitation;
use Illuminate\Http\JsonResponse;

class ShowTenantInvitationController extends Controller
{
    public function __invoke(string $token): JsonResponse
    {
        $invitation = TenantInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        $isAvailable = $invitation !== null
            && $invitation->isAvailable();

        return response()
            ->json([
                'data' => [
                    'is_available' => $isAvailable,
                    'email' => $isAvailable ? $invitation->email : null,
                ],
            ], $isAvailable ? 200 : 410)
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
