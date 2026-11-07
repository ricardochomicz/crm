<?php

namespace App\Http\Requests\Commission;

use App\Enums\OrderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCommissionTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        return $tenant !== null && (bool) $this->user()?->can('viewCommissions', $tenant);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'operator_id' => ['nullable', 'integer'],
            'order_type' => ['nullable', Rule::enum(OrderType::class)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
