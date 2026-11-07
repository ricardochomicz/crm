<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicTenantInvitationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $available = $this->resource?->isAvailable() ?? false;

        return [
            'is_available' => $available,
            'email' => $available ? $this->resource->email : null,
        ];
    }
}
