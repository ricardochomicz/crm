<?php

namespace App\Http\Resources\Commission;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommercialGoalTierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attributes = $this->resource->attributesToArray();

        return [
            'id' => $attributes['id'] ?? null,
            'commercial_goal_id' => $attributes['commercial_goal_id'] ?? null,
            'minimum' => $attributes['minimum'] ?? null,
            'percentage' => $attributes['percentage'] ?? null,
            'created_at' => $attributes['created_at'] ?? null,
            'updated_at' => $attributes['updated_at'] ?? null,
        ];
    }
}
