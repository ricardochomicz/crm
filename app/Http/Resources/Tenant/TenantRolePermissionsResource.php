<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantRolePermissionsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'catalog' => $this->resource['catalog'],
            'roles' => $this->resource['roles'],
            'can_edit' => $this->resource['can_edit'],
        ];
    }
}
