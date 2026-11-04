<?php

namespace App\Http\Requests\Tenant;

use App\Enums\TenantPermission;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTenantRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        return $tenant instanceof Tenant && ($this->user()?->can('managePermissions', $tenant) ?? false);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array', 'list', 'max:'.count(TenantPermission::cases())],
            'permissions.*' => ['required', 'string', 'distinct', Rule::in(TenantPermission::keys())],
            'version' => ['required', 'integer', 'min:0'],
        ];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $selected = $this->input('permissions');
            foreach (TenantPermission::catalog() as $permission) {
                if (in_array($permission['key'], $selected, true)
                    && array_diff($permission['requires'], $selected) !== []) {
                    $validator->errors()->add('permissions', 'Habilite a visualização do módulo antes de permitir suas alterações.');
                }
            }
        }];
    }
}
