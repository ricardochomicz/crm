<?php

namespace App\Http\Requests\Commission;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexOrderCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        return $tenant !== null && (bool) $this->user()?->can('viewCommissions', $tenant);
    }

    public function rules(): array
    {
        return [
            'revision' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['ESTIMATED', 'ELIGIBLE', 'PENDING_RULES'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
