<?php

namespace App\Http\Requests\Tenant;

use App\Enums\TenantUserRole;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantMemberRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        return $tenant instanceof Tenant
            && ($this->user()?->can('updateMemberRole', $tenant) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => [
                'required',
                Rule::enum(TenantUserRole::class)->only([
                    TenantUserRole::Manager,
                    TenantUserRole::Consultant,
                    TenantUserRole::Assistant,
                ]),
            ],
        ];
    }
}
