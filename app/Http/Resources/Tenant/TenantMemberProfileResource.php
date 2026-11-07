<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantMemberProfileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'cpf' => $this->cpf,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'mobile_phone' => $this->mobile_phone,
            'secondary_phone' => $this->secondary_phone,
            'hired_at' => $this->hired_at?->format('Y-m-d'),
            'employee_code' => $this->employee_code,
        ];
    }
}
