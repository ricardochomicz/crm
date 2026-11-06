<?php
namespace App\Http\Resources\Tenant;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\TenantStatus;
class PlatformTenantDetailsResource extends JsonResource
{
    /** @param array<string, mixed> $context */
    public function __construct(Tenant $resource, private array $context)
    {
        parent::__construct($resource);
    }
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $subscription = $this->subscription;
        return [
                'id' => $this->id,
                'name' => $this->name,
                'trade_name' => $this->trade_name,
                'type' => $this->type->value,
                'document' => $this->document,
                'email' => $this->email,
                'phone' => $this->phone,
                'timezone' => $this->timezone,
                'status' => $this->status->value,
                'users_count' => $this->users_count,
                'teams_count' => $this->teams_count,
                'owner' => $this->context['owner'] === null ? null : [
                    'id' => $this->context['owner']->id,
                    'name' => $this->context['owner']->name,
                    'email' => $this->context['owner']->email,
                    'membership_status' => $this->context['owner']->membership->status->value,
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
                    'allows_operational_writes' => $this->context['allowsWrites'],
                ],
                'access' => [
                    'tenant_active' => $this->status === TenantStatus::Active,
                    'operational_writes_available' => $this->status
                        === TenantStatus::Active && $this->context['allowsWrites'],
                ],
                'created_at' => $this->created_at?->toIso8601String(),
                'updated_at' => $this->updated_at?->toIso8601String(),
            ];
    }
}
