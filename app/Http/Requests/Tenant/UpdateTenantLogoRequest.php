<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->attributes->get('active_tenant')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
