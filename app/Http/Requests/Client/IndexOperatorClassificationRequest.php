<?php

namespace App\Http\Requests\Client;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

class IndexOperatorClassificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        if ($tenant instanceof Tenant) {
            return ($this->user()?->can('viewClients', $tenant) ?? false)
                || ($this->user()?->can('viewCommissions', $tenant) ?? false);
        }

        return (bool) $this->user()?->is_platform_admin;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'operator_id' => ['nullable', 'integer', 'exists:operators,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
