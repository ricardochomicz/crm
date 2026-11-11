<?php

namespace App\Http\Requests\Commission;

use App\Enums\CommercialRole;
use App\Rules\ValidCommissionOrderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTeamCommissionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        return $tenant !== null && (bool) $this->user()?->can('viewTeamCommissions', $tenant);
    }

    public function rules(): array
    {
        $tenant = $this->attributes->get('active_tenant');

        return [
            'operator_id' => ['nullable', 'integer'],
            'order_type' => ['nullable', 'string', new ValidCommissionOrderType(activeOnly: false)],
            'commercial_role' => ['nullable', Rule::enum(CommercialRole::class)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
