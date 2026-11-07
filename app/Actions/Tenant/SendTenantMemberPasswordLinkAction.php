<?php

namespace App\Actions\Tenant;

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Throwable;

class SendTenantMemberPasswordLinkAction
{
    public function execute(User $user): bool
    {
        try {
            return Password::broker('users')->sendResetLink([
                'email' => $user->email,
            ]) === Password::RESET_LINK_SENT;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
