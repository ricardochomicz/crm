<?php

namespace App\Actions\Tenant;

use App\Enums\TenantInvitationStatus;
use App\Models\Tenant;
use App\Models\Subscription;

class ShowTenantSubscriptionAction
{
    /** @return array{subscription: Subscription|null, usersCount: int, reservedSeatsCount: int} */
    public function execute(Tenant $tenant): array
    {
        $subscription = $tenant->subscription()
            ->with('plan:id,code,name')
            ->first();

        if ($subscription === null) {
            return [
                'subscription' => null,
                'usersCount' => 0,
                'reservedSeatsCount' => 0,
            ];
        }

        return [
            'subscription' => $subscription,
            'usersCount' => $tenant->users()->count(),
            'reservedSeatsCount' => $tenant->invitations()
                ->where('status', TenantInvitationStatus::Pending->value)
                ->where('expires_at', '>', now())
                ->count(),
        ];
    }
}
