<?php

namespace App\Http\Requests\Product;

use App\Enums\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'operator_id' => ['sometimes', 'integer'],
            'category' => ['sometimes', 'string', Rule::enum(ProductCategory::class)],
            'search' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
