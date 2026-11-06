<?php

namespace App\Http\Requests\Commission;

use App\Enums\CommercialRole;
use App\Enums\OrderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCommercialGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        return $tenant !== null && (bool) $this->user()?->can('manageCommissions', $tenant);
    }

    public function rules(): array
    {
        $tenant = $this->attributes->get('active_tenant');

        return [
            'operator_id' => ['required', 'integer', Rule::exists('tenant_operators', 'operator_id')->where('tenant_id', $tenant->id)],
            'order_type' => ['required', Rule::enum(OrderType::class)],
            'commercial_role' => ['required', Rule::enum(CommercialRole::class)],
            'metric' => ['required', Rule::in(['QUANTITY', 'REVENUE'])],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'is_active' => ['required', 'boolean'],
            'tiers' => ['required', 'array', 'min:1', 'max:30'],
            'tiers.*.minimum' => ['required', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'],
            'tiers.*.percentage' => ['required', 'numeric', 'min:0', 'max:9999.99', 'decimal:0,2'],
        ];
    }
}
