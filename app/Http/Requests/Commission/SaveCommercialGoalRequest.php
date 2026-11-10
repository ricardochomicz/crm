<?php

namespace App\Http\Requests\Commission;

use App\Enums\CommercialRole;
use App\Enums\OrderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCommercialGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        return $tenant !== null && (bool) $this->user()?->can('manageCommercialGoals', $tenant);
    }

    public function rules(): array
    {
        $tenant = $this->attributes->get('active_tenant');

        return [
            'operator_id' => ['required', 'integer', Rule::exists('tenant_operators', 'operator_id')->where('tenant_id', $tenant->id)],
            'order_type' => ['required', function (string $attribute, mixed $value, \Closure $fail): void {
                if (!is_string($value) || (!OrderType::tryFrom($value) && !\App\Models\OrderType::query()->whereKey($value)->where('is_active', true)->exists())) {
                    $fail('Selecione um tipo de negociação válido e ativo.');
                }
            }],
            'category' => ['nullable', 'string', 'max:80'],
            'negotiation_type_ids' => ['nullable', 'array', 'min:1', 'max:30'],
            'negotiation_type_ids.*' => ['required', 'string', 'distinct', Rule::exists('order_types', 'id')->where('is_active', true)],
            'target_scope' => ['required', Rule::in(['COMPANY', 'CONSULTANT', 'SUPERVISOR'])],
            'responsible_membership_id' => ['required_unless:target_scope,COMPANY', 'nullable', 'uuid', Rule::exists('tenant_users', 'id')->where('tenant_id', $tenant->id)->where('status', 'ACTIVE')],
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
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $data = $this->validated();
            $minimums = array_map(fn ($tier) => (float) $tier['minimum'], $data['tiers']);

            if (count(array_unique($minimums)) !== count($minimums) || min($minimums) !== 0.0) {
                $validator->errors()->add('tiers', 'As faixas devem começar em zero e não podem repetir valores mínimos.');
            }

            if ($data['metric'] === 'QUANTITY' && collect($data['tiers'])->contains(
                fn ($tier) => floor((float) $tier['minimum']) !== (float) $tier['minimum']
            )) {
                $validator->errors()->add('tiers', 'Metas por quantidade exigem limites inteiros.');
            }
        }];
    }
}
