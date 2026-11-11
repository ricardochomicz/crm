<?php

namespace App\Http\Requests\Commercial;

use Illuminate\Foundation\Http\FormRequest;

class ActivateOrderItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        return $tenant !== null && (bool) $this->user()?->can('manageOrders', $tenant);
    }

    public function rules(): array
    {
        return [
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['required', 'uuid', 'distinct'],
            'activated_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }
}
