<?php

namespace App\Http\Resources\Commercial;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommercialOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attributes = $this->resource->attributesToArray();

        return [
            'id' => $attributes['id'] ?? null,
            'tenant_id' => $attributes['tenant_id'] ?? null,
            'operator_id' => $attributes['operator_id'] ?? null,
            'product_id' => $attributes['product_id'] ?? null,
            'order_type' => $attributes['order_type'] ?? null,
            'customer_name' => $attributes['customer_name'] ?? null,
            'customer_document' => $attributes['customer_document'] ?? null,
            'monthly_amount' => $attributes['monthly_amount'] ?? null,
            'line_quantity' => $attributes['line_quantity'] ?? null,
            'status' => $attributes['status'] ?? null,
            'sold_on' => $attributes['sold_on'] ?? null,
            'notes' => $attributes['notes'] ?? null,
            'created_at' => $attributes['created_at'] ?? null,
            'updated_at' => $attributes['updated_at'] ?? null,
            'operator' => $this->whenLoaded('operator'),
            'product' => $this->whenLoaded('product'),
            'participants' => CommercialOrderParticipantResource::collection($this->whenLoaded('participants')),
        ];
    }
}
