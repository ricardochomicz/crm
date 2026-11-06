<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
                $subscription = $this->subscription;

                return [
                    'id' => $this->id,
                    'name' => $this->name,
                    'type' => $this->type->value,
                    'document' => $this->document,
                    'status' => $this->status->value,
                    'users_count' => $this->users_count,
                    'subscription' => $subscription === null ? null : [
                        'status' => $subscription->status->value,
                        'plan_name' => $subscription->plan?->name,
                        'allows_operational_writes' => $subscription
                            ->allowsOperationalWrites(),
                    ],
                    'created_at' => $this->created_at
                        ?->toIso8601String(),
                ];
    }
}
