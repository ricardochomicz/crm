<?php

namespace App\Http\Resources\SubscriptionPlan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
                    'id' => $this->id,
                    'code' => $this->code,
                    'name' => $this->name,
                    'description' => $this->description,
                    'monthly_price' => $this->monthly_price,
                    'currency' => $this->currency,
                    'user_limit' => $this->user_limit,
                    'storage_limit_mb' => $this->storage_limit_mb,
                ];
    }
}
