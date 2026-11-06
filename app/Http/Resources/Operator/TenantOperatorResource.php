<?php

namespace App\Http\Resources\Operator;

use App\Models\Operator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Operator
 */
class TenantOperatorResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'is_active' => (bool) $this->is_active,
            'selected' => (bool) ($this->selected ?? false),
        ];
    }
}
