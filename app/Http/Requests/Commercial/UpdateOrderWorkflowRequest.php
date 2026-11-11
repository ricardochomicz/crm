<?php

namespace App\Http\Requests\Commercial;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        return $tenant !== null && (bool) $this->user()?->can('manageOrders', $tenant);
    }

    public function rules(): array
    {
        return [
            'order_status_id' => ['required', 'uuid'],
            'correction_reason' => ['nullable', 'string', 'max:2000'],
            'expected_activation_at' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
