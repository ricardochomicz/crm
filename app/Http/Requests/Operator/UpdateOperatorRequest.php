<?php

namespace App\Http\Requests\Operator;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOperatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'slug' => ['sometimes', 'required', 'alpha_dash', 'max:160', Rule::unique('operators', 'slug')->ignore($this->route('operator')->id)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
