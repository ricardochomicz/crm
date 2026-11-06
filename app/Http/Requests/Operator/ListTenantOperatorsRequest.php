<?php

namespace App\Http\Requests\Operator;

use App\Enums\TenantUserRole;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

class ListTenantOperatorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');
        if (! ($tenant instanceof Tenant) || $this->user() === null) {
            return false;
        }

        $membership = $tenant->users()->where('users.id', $this->user()->id)->first();
        $isOwner = $membership && $membership->membership->role === TenantUserRole::Owner;
        $canViewCommissions = $this->user()->can('viewCommissions', $tenant);

        return $isOwner || $this->user()->can('viewProducts', $tenant) || $canViewCommissions;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:180'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}

