<?php

namespace App\Http\Resources\Commission;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommercialGoalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attributes = $this->resource->attributesToArray();

        return [
            'id' => $attributes['id'] ?? null,
            'tenant_id' => $attributes['tenant_id'] ?? null,
            'operator_id' => $attributes['operator_id'] ?? null,
            'order_type' => $attributes['order_type'] ?? null,
            'category' => $attributes['category'] ?? null,
            'negotiation_type_ids' => $this->negotiation_type_ids ?? [],
            'commercial_role' => $attributes['commercial_role'] ?? null,
            'metric' => $attributes['metric'] ?? null,
            'starts_on' => $attributes['starts_on'] ?? null,
            'ends_on' => $attributes['ends_on'] ?? null,
            'is_active' => $attributes['is_active'] ?? null,
            'created_at' => $attributes['created_at'] ?? null,
            'updated_at' => $attributes['updated_at'] ?? null,
            'operator' => $this->whenLoaded('operator'),
            'tiers' => CommercialGoalTierResource::collection($this->whenLoaded('tiers')),
        ];
    }
}
