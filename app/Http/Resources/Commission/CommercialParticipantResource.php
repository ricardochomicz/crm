<?php

namespace App\Http\Resources\Commission;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommercialParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attributes = $this->resource->attributesToArray();

        return [
            'id' => $attributes['id'] ?? null,
            'tenant_id' => $attributes['tenant_id'] ?? null,
            'role' => $attributes['role'] ?? null,
            'person_type' => $attributes['person_type'] ?? null,
            'name' => $attributes['name'] ?? null,
            'document' => $attributes['document'] ?? null,
            'email' => $attributes['email'] ?? null,
            'phone' => $attributes['phone'] ?? null,
            'is_active' => $attributes['is_active'] ?? null,
            'notes' => $attributes['notes'] ?? null,
            'created_at' => $attributes['created_at'] ?? null,
            'updated_at' => $attributes['updated_at'] ?? null,
        ];
    }
}
