<?php

namespace App\Http\Requests\Product;

use App\Enums\ProductCategory;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');
        return $tenant instanceof Tenant && $this->user()->can($this->isMethod('POST') ? 'createProducts' : 'updateProducts', $tenant);
    }
    public function rules(): array
    {
        $tenant = $this->attributes->get('active_tenant');
        return [
            'operator_id' => ['required', 'integer', Rule::exists('tenant_operators', 'operator_id')->where('tenant_id', $tenant->id), Rule::exists('operators', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'category' => ['required', Rule::enum(ProductCategory::class)],
            'price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
