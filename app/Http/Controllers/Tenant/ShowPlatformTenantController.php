<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\TenantStatus;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ShowPlatformTenantController extends Controller
{
    public function __invoke(Tenant $tenant): JsonResponse
    {
        Gate::authorize('access-platform-admin');

        $tenant->load('subscription.plan:id,code,name');
        $tenant->loadCount(['users', 'teams']);

        $owner = $tenant->users()
            ->wherePivot('role', TenantUserRole::Owner->value)
            ->orderBy('users.id')
            ->first();

        $subscription = $tenant->subscription;

        $allowsWrites = $subscription?->allowsOperationalWrites() ?? false;

        return response()->json([
            'data' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'trade_name' => $tenant->trade_name,
                'type' => $tenant->type->value,
                'document' => $tenant->document,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'timezone' => $tenant->timezone,
                'status' => $tenant->status->value,
                'users_count' => $tenant->users_count,
                'teams_count' => $tenant->teams_count,
                'owner' => $owner === null ? null : [
                    'id' => $owner->id,
                    'name' => $owner->name,
                    'email' => $owner->email,
                    'membership_status' => $owner->membership->status->value,
                ],
                'subscription' => $subscription === null ? null : [
                    'id' => $subscription->id,
                    'status' => $subscription->status->value,
                    'plan' => [
                        'id' => $subscription->subscription_plan_id,
                        'code' => $subscription->plan?->code,
                        'name' => $subscription->plan?->name,
                    ],
                    'monthly_price' => $subscription->monthly_price,
                    'currency' => $subscription->currency,
                    'user_limit' => $subscription->user_limit,
                    'storage_limit_mb' => $subscription->storage_limit_mb,
                    'trial_ends_at' => $subscription->trial_ends_at
                        ?->toIso8601String(),
                    'current_period_starts_at' => $subscription
                        ->current_period_starts_at?->toIso8601String(),
                    'current_period_ends_at' => $subscription
                        ->current_period_ends_at?->toIso8601String(),
                    'cancel_at_period_end' => $subscription->cancel_at_period_end,
                    'cancelled_at' => $subscription->cancelled_at
                        ?->toIso8601String(),
                    'allows_operational_writes' => $allowsWrites,
                ],
                'access' => [
                    'tenant_active' => $tenant->status === TenantStatus::Active,
                    'operational_writes_available' => $tenant->status
                        === TenantStatus::Active && $allowsWrites,
                ],
                'created_at' => $tenant->created_at?->toIso8601String(),
                'updated_at' => $tenant->updated_at?->toIso8601String(),
            ],
        ]);
    }
}
