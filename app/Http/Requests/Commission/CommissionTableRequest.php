<?php

namespace App\Http\Requests\Commission;

use App\Enums\OrderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommissionTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        return $tenant !== null && (bool) $this->user()?->can($this->isMethod('GET') ? 'viewCommissions' : 'manageCommissions', $tenant);
    }

    public function rules(): array
    {
        $tenant = $this->attributes->get('active_tenant');
        if ($this->isMethod('GET')) {
            return ['search' => ['nullable', 'string', 'max:200'], 'operator_id' => ['nullable', 'integer'], 'order_type' => ['nullable', Rule::enum(OrderType::class)], 'is_active' => ['nullable', 'boolean']];
        }
        if ($this->route('commissionId') === 'settings') {
            return ['default_deduction_percentage' => ['required', 'numeric', 'min:0', 'max:100']];
        }

        return ['order_type' => ['required', Rule::enum(OrderType::class)],
            'operator_id' => ['required', 'integer', Rule::exists('tenant_operators', 'operator_id')->where('tenant_id', $tenant->id)],
            'percentage' => 'required|numeric|min:0|max:9999.99|decimal:0,2',
            'deduction_percentage' => 'nullable|numeric|min:0|max:100|decimal:0,2',
            'is_active' => 'sometimes|boolean', 'description' => 'nullable|string|max:10000'];
    }
}
