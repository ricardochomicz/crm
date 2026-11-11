<?php

namespace App\Http\Resources\OrderStatus;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code?->value,
            'is_initial' => (bool) $this->is_initial,
        ];
    }
}
