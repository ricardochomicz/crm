<?php

namespace App\Http\Requests\Tenant;

use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        return $tenant instanceof Tenant
            && ($this->user('web')?->can('addMember', $tenant) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:254',
                Rule::unique(User::class, 'email'),
            ],
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
