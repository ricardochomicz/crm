<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
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

        return response()->json([
            'data' => [
                'id' => $subscription->id,
                'tenant_id' => $tenant->id,
                'status' => $subscription->status->value,
                'plan' => [
                    'id' => $subscription->subscription_plan_id,
                    'code' => $subscription->plan?->code,
                    'name' => $subscription->plan?->name,
                ],
                'monthly_price' => $subscription->monthly_price,
                'currency' => $subscription->currency,
                'limits' => [
                    'users' => $subscription->user_limit,
                    'storage_mb' => $subscription->storage_limit_mb,
                ],
                'usage' => [
                    'users' => $usersCount,
                ],
                'remaining' => [
                    'users' => max(
                        0,
                        $subscription->user_limit - $usersCount,
                    ),
                ],
                'started_at' => $subscription->started_at
                    ?->toIso8601String(),
                'trial_ends_at' => $subscription->trial_ends_at
                    ?->toIso8601String(),
                'current_period_starts_at' => $subscription
                    ->current_period_starts_at?->toIso8601String(),
                'current_period_ends_at' => $subscription
                    ->current_period_ends_at?->toIso8601String(),
                'cancel_at_period_end' => $subscription->cancel_at_period_end,
                'cancelled_at' => $subscription->cancelled_at
                    ?->toIso8601String(),
                'allows_operational_writes' => $subscription
                    ->allowsOperationalWrites(),
            ],
        ]);
    }
}
