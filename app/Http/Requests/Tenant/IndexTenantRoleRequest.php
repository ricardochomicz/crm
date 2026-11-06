<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

class IndexTenantRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');
        if (! ($tenant instanceof Tenant) || $this->user() === null) {
            return false;
        }

        return $this->user()->can('viewMembers', $tenant)
            || $this->user()->can('createInvitation', $tenant)
            || $this->user()->can('managePermissions', $tenant);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:180'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
