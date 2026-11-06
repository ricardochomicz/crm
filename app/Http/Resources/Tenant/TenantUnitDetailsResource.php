<?php
namespace App\Http\Resources\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class TenantUnitDetailsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'tenant_id' => $this->tenant_id,
            'name' => $this->name, 'is_headquarters' => $this->is_headquarters,
            'email' => $this->email, 'phone' => $this->phone,
            'address' => $this->address, 'city' => $this->city, 'state' => $this->state,
            'created_at' => $this->created_at, 'updated_at' => $this->updated_at,
        ];
    }
}
