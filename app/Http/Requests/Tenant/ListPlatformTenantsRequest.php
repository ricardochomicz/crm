<?php

namespace App\Http\Requests\Tenant;

use App\Enums\TenantStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPlatformTenantsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('access-platform-admin') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:180'],
            'document' => ['sometimes', 'nullable', 'string', 'max:14'],
            'status' => [
                'sometimes',
                'nullable',
                Rule::enum(TenantStatus::class),
            ],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
