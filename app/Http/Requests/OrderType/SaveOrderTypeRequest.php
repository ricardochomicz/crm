<?php

namespace App\Http\Requests\OrderType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveOrderTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-platform-admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('order_types', 'name')->ignore($this->route('orderTypeId'))],
            'requires_number' => ['required', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:999999'],
        ];
    }
}
