<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class ListTenantPermissionChangesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('managePermissions', $this->attributes->get('active_tenant')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'role' => ['sometimes', 'string', \Illuminate\Validation\Rule::in(['OWNER', 'MANAGER', 'CONSULTANT', 'ASSISTANT'])],
        ];
    }
}
