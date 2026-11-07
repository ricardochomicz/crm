<?php

namespace App\Http\Requests\Commission;

use App\Enums\CommercialRole;
use App\Enums\OrderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTeamCommissionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        return $tenant !== null && (bool) $this->user()?->can('manageTeamCommissions', $tenant);
    }

    public function rules(): array
    {
        $tenant = $this->attributes->get('active_tenant');

        return [
            'operator_id' => ['required', 'integer', Rule::exists('tenant_operators', 'operator_id')->where('tenant_id', $tenant->id)],
            'order_type' => ['required', Rule::enum(OrderType::class)],
            'commercial_role' => ['required', Rule::enum(CommercialRole::class)],
            'percentage' => ['required', 'numeric', 'min:0', 'max:9999.99', 'decimal:0,2'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
