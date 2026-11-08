<?php

namespace App\Http\Requests\Tenant;

use App\Enums\TenantPermission;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTenantRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        return $tenant instanceof Tenant && ($this->user()?->can('managePermissions', $tenant) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'code' => ['sometimes', 'string', 'regex:/^[A-Z][A-Z0-9_]*$/', 'max:40'],
            'legacy_role' => ['sometimes', Rule::in(['MANAGER', 'CONSULTANT', 'ASSISTANT'])],
            'version' => [Rule::requiredIf($this->isMethod('PATCH')), 'integer', 'min:1'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in(TenantPermission::keys())],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
