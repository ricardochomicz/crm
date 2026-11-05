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
            'membership_ids' => ['present', 'array', 'max:1000'],
            'membership_ids.*' => ['required', 'uuid', 'distinct', Rule::exists('tenant_users', 'id')->where('tenant_id', $tenant->id)],
            'team_ids' => ['present', 'array', 'max:1000'],
            'team_ids.*' => ['required', 'uuid', 'distinct', Rule::exists('teams', 'id')->where('tenant_id', $tenant->id)],
        ];
    }
}
