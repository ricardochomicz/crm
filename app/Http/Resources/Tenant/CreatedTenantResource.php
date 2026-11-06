<?php
namespace App\Http\Resources\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class CreatedTenantResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
                'id' => $this->id,
                'name' => $this->name,
                'type' => $this->type->value,
            ];
    }
}
