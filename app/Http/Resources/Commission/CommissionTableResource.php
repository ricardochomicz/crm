<?php

namespace App\Http\Resources\Commission;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommissionTableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attributes = $this->resource->attributesToArray();

        return [
            'id' => $attributes['id'] ?? null,
            'tenant_id' => $attributes['tenant_id'] ?? null,
            'operator_id' => $attributes['operator_id'] ?? null,
            'name' => $attributes['name'] ?? null,
            'order_type' => $attributes['order_type'] ?? null,
            'percentage' => $attributes['percentage'] ?? null,
            'deduction_percentage' => $attributes['deduction_percentage'] ?? null,
            'is_active' => $attributes['is_active'] ?? null,
            'description' => $attributes['description'] ?? null,
            'created_at' => $attributes['created_at'] ?? null,
            'updated_at' => $attributes['updated_at'] ?? null,
            'operator' => $this->whenLoaded('operator'),
        ];
    }
}
