<?php

namespace App\Http\Controllers\Tenant\Subscriptions;

use App\Enums\TenantInvitationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\SubscriptionResource;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\JsonResponse;

class ShowTenantSubscriptionController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        Gate::forUser($request->user())
            ->authorize('viewSubscription', $tenant);

        $subscription = $tenant->subscription()
            ->with('plan:id,code,name')
            ->first();

        if ($subscription === null) {
            return response()->json([
                'message' => 'A empresa não possui assinatura.',
                'code' => 'subscription_not_found',
            ], 404);
        }

        $usersCount = $tenant->users()->count();

        $reservedSeatsCount = $tenant->invitations()
            ->where('status', TenantInvitationStatus::Pending->value)
            ->where('expires_at', '>', now())
            ->count();

        return response()->json([
            'data' => new SubscriptionResource($subscription, ['usersCount' => $usersCount, 'reservedSeatsCount' => $reservedSeatsCount]),
        ]);
    }
}
