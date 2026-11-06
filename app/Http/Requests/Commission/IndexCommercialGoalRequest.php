<?php

namespace App\Http\Requests\Commission;

use App\Enums\CommercialRole;
use App\Enums\OrderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCommercialGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        return $tenant !== null && (bool) $this->user()?->can('viewCommissions', $tenant);
    }

    public function rules(): array
    {
        $tenant = $this->attributes->get('active_tenant');

        return [
            'operator_id' => ['nullable', 'integer'],
            'order_type' => ['nullable', Rule::enum(OrderType::class)],
            'commercial_role' => ['nullable', Rule::enum(CommercialRole::class)],
            'metric' => ['nullable', Rule::in(['QUANTITY', 'REVENUE'])],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
