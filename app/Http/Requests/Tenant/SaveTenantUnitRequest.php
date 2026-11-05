<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

class SaveTenantUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        return $tenant instanceof Tenant && ($this->user()?->can('manageUnits', $tenant) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'email' => ['nullable', 'email', 'max:254'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }
}
