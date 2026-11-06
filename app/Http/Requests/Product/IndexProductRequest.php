<?php

namespace App\Http\Requests\Product;

use App\Enums\ProductCategory;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class IndexProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        return $tenant !== null && (bool) $this->user()?->can('viewProducts', $tenant);
    }

    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'operator_id' => ['sometimes', 'integer'],
            'category' => ['sometimes', Rule::enum(ProductCategory::class)],
            'search' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
