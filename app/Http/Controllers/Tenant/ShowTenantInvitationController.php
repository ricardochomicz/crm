<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\TenantInvitation;
use Illuminate\Http\Response;

class ShowTenantInvitationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(string $token): Response
    {
        $invitation = TenantInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        $isAvailable = $invitation !== null
            && $invitation->isAvailable();

        return response()
            ->view('tenant-invitations.show', [
                'isAvailable' => $isAvailable,
            ], $isAvailable ? 200 : 410)
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
