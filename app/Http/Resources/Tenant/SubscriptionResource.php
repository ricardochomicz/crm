<?php
namespace App\Http\Resources\Tenant;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class SubscriptionResource extends JsonResource
{
    /** @param array<string, mixed> $context */
    public function __construct(Subscription $resource, private array $context)
    {
        parent::__construct($resource);
    }
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
                'id' => $this->id,
                'tenant_id' => $this->tenant_id,
                'status' => $this->status->value,
                'plan' => [
                    'id' => $this->subscription_plan_id,
                    'code' => $this->plan?->code,
                    'name' => $this->plan?->name,
                ],
                'monthly_price' => $this->monthly_price,
                'currency' => $this->currency,
                'limits' => [
                    'users' => $this->user_limit,
                    'storage_mb' => $this->storage_limit_mb,
                ],
                'usage' => [
                    'users' => $this->context['usersCount'],
                    'reserved_invitations' => $this->context['reservedSeatsCount'],
                ],
                'remaining' => [
                    'users' => max(
                        0,
                        $this->user_limit - $this->context['usersCount'] - $this->context['reservedSeatsCount'],
                    ),
                ],
                'started_at' => $this->started_at
                    ?->toIso8601String(),
                'trial_ends_at' => $this->trial_ends_at
                    ?->toIso8601String(),
                'current_period_starts_at' => $subscription
                    ->current_period_starts_at?->toIso8601String(),
                'current_period_ends_at' => $subscription
                    ->current_period_ends_at?->toIso8601String(),
                'cancel_at_period_end' => $this->cancel_at_period_end,
                'cancelled_at' => $this->cancelled_at
                    ?->toIso8601String(),
                'allows_operational_writes' => $subscription
                    ->allowsOperationalWrites(),
            ];
    }
}
