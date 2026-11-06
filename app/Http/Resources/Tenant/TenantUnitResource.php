<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantUnitResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
                'id' => $this->id, 'name' => $this->name, 'is_headquarters' => (bool) $this->is_headquarters,
                'email' => $this->email, 'phone' => $this->phone, 'address' => $this->address, 'city' => $this->city, 'state' => $this->state,
                'members_count' => $this->members_count, 'teams_count' => $this->teams_count,
            ];
    }
}
