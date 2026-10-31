<?php

namespace App\Http\Requests\Tenant;

use App\Enums\TenantUserStatus;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantMemberStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        return $tenant instanceof Tenant
            && ($this->user()?->can('updateMemberStatus', $tenant) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::enum(TenantUserStatus::class)->only([
                    TenantUserStatus::Active,
                    TenantUserStatus::Suspended,
                ]),
            ],
        ];
    }
}
