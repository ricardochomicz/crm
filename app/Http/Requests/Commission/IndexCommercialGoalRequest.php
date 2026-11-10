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

        return $tenant !== null && (bool) $this->user()?->can('viewCommercialGoals', $tenant);
    }

    public function rules(): array
    {
        $tenant = $this->attributes->get('active_tenant');

        return [
            'operator_id' => ['nullable', 'integer'],
            'order_type' => ['nullable', function (string $attribute, mixed $value, \Closure $fail): void {
                if (!is_string($value) || (!OrderType::tryFrom($value) && !\App\Models\OrderType::query()->whereKey($value)->where('is_active', true)->exists())) {
                    $fail('Selecione um tipo de negociação válido e ativo.');
                }
            }],
            'commercial_role' => ['nullable', Rule::enum(CommercialRole::class)],
            'metric' => ['nullable', Rule::in(['QUANTITY', 'REVENUE'])],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
