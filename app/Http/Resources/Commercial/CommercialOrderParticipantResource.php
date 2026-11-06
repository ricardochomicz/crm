<?php

namespace App\Http\Resources\Commercial;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommercialOrderParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attributes = $this->resource->attributesToArray();

        return [
            'id' => $attributes['id'] ?? null,
            'commercial_order_id' => $attributes['commercial_order_id'] ?? null,
            'role' => $attributes['role'] ?? null,
            'tenant_user_id' => $attributes['tenant_user_id'] ?? null,
            'commercial_participant_id' => $attributes['commercial_participant_id'] ?? null,
            'participant_name' => $attributes['participant_name'] ?? null,
            'created_at' => $attributes['created_at'] ?? null,
            'updated_at' => $attributes['updated_at'] ?? null,
        ];
    }
}
