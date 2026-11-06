<?php

namespace App\Http\Requests\Operator;

use Illuminate\Foundation\Http\FormRequest;

class SyncTenantOperatorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'operator_ids' => ['required', 'array'],
            'operator_ids.*' => ['required', 'integer', 'distinct', 'exists:operators,id'],
        ];
    }
}
