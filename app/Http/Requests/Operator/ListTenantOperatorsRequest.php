<?php

namespace App\Http\Requests\Operator;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

class ListTenantOperatorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');
        if (! ($tenant instanceof Tenant) || $this->user() === null) {
            return false;
        }

        return (bool) $this->user()->can('viewTenantOperators', $tenant);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:180'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}

