<?php

namespace App\Http\Requests\Commission;

use App\Enums\OrderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCommissionTableRequest extends FormRequest
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
            'order_type' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (OrderType::tryFrom((string) $value) !== null) return;
                if (! \App\Models\OrderType::query()->whereKey($value)->where('is_active', true)->exists()) {
                    $fail('Selecione um tipo de pedido válido e ativo.');
                }
            }],
            'operator_id' => ['required', 'integer', Rule::exists('tenant_operators', 'operator_id')->where('tenant_id', $tenant->id)],
            'percentage' => ['required', 'numeric', 'min:0', 'max:9999.99', 'decimal:0,2'],
            'deduction_percentage' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
