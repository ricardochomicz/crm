<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantMemberProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        return $tenant instanceof Tenant
            && ($this->user()?->can('updateMemberProfiles', $tenant) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('cpf') && is_string($this->input('cpf'))) {
            $this->merge(['cpf' => preg_replace('/\D/', '', $this->input('cpf')) ?: null]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cpf' => ['sometimes', 'nullable', 'string', 'size:11', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || preg_match('/^(\d)\1{10}$/', $value)) {
                    $fail('Informe um CPF válido.');
                    return;
                }
                for ($length = 9; $length <= 10; $length++) {
                    $sum = 0;
                    for ($i = 0; $i < $length; $i++) {
                        $sum += (int) $value[$i] * ($length + 1 - $i);
                    }
                    $digit = ($sum * 10) % 11;
                    if ($digit === 10) $digit = 0;
                    if ((int) $value[$length] !== $digit) {
                        $fail('Informe um CPF válido.');
                        return;
                    }
                }
            }],
            'birth_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'mobile_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'secondary_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'hired_at' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'employee_code' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
