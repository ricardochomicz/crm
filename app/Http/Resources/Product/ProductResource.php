<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'operator_id' => $this->operator_id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'price' => $this->price,
            'is_active' => $this->is_active,
            'operator' => $this->whenLoaded('operator'),
            'promotions' => ProductPromotionResource::collection($this->whenLoaded('promotions')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
