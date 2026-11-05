<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantUnitAssignmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        return $tenant instanceof Tenant && ($this->user()?->can('manageUnits', $tenant) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenant = $this->attributes->get('active_tenant');

        return [
            'kind' => ['required', Rule::in(['members', 'teams'])],
            'operation' => ['required', Rule::in(['add', 'remove'])],
            'ids' => ['required', 'array', 'min:1', 'max:1000'],
            'ids.*' => ['required', 'uuid', 'distinct', Rule::exists($this->input('kind') === 'teams' ? 'teams' : 'tenant_users', 'id')->where('tenant_id', $tenant->id)],
        ];
    }
}
