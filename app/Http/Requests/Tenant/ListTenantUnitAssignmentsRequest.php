<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTenantUnitAssignmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        return $tenant instanceof Tenant && ($this->user()?->can('manageUnits', $tenant) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(['members', 'teams'])],
            'mode' => ['required', Rule::in(['linked', 'available'])],
            'q' => ['nullable', 'string', 'max:180'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
