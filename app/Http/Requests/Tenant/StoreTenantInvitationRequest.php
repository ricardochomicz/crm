<?php

namespace App\Http\Requests\Tenant;

use App\Enums\TenantUserRole;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreTenantInvitationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        return $tenant instanceof Tenant
            && ($this->user()?->can('addMember', $tenant) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge([
                'email' => Str::lower(trim($this->input('email'))),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email',
                'max:254',
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
